<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Tests\Serializer;

use Alchemy\AclBundle\Entity\AccessControlEntry;
use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AclBundle\Repository\GroupRepositoryInterface;
use Alchemy\AclBundle\Repository\UserRepositoryInterface;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AclBundle\Serializer\AceSerializer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AceSerializerTest extends TestCase
{
    private UserRepositoryInterface&MockObject $userRepository;
    private GroupRepositoryInterface&MockObject $groupRepository;
    private AceSerializer $serializer;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->groupRepository = $this->createMock(GroupRepositoryInterface::class);
        $this->serializer = new AceSerializer($this->userRepository, $this->groupRepository);
    }

    public function testSerializeUserAce(): void
    {
        $ace = $this->createAce(AccessControlEntryInterface::TYPE_USER_VALUE, 'u-1');
        $ace->setParentId('p:1');
        $ace->setMetadata(['reason' => 'shared']);

        $this->userRepository
            ->expects($this->once())
            ->method('getUser')
            ->with('u-1')
            ->willReturn(['id' => 'u-1', 'username' => 'alice']);
        $this->groupRepository
            ->expects($this->never())
            ->method('getGroup');

        $this->assertSame([
            'id' => $ace->getId(),
            'userType' => 'user',
            'userId' => 'u-1',
            'objectType' => 'publication',
            'objectId' => 'pub-42',
            'mask' => PermissionInterface::VIEW | PermissionInterface::EDIT,
            'parentId' => 'p:1',
            'metadata' => ['reason' => 'shared'],
            'user' => ['id' => 'u-1', 'username' => 'alice'],
        ], $this->serializer->serialize($ace));
    }

    public function testSerializeGroupAce(): void
    {
        $ace = $this->createAce(AccessControlEntryInterface::TYPE_GROUP_VALUE, 'g-1');

        $this->groupRepository
            ->expects($this->once())
            ->method('getGroup')
            ->with('g-1')
            ->willReturn(['id' => 'g-1', 'name' => 'editors']);
        $this->userRepository
            ->expects($this->never())
            ->method('getUser');

        $payload = $this->serializer->serialize($ace);

        $this->assertSame('group', $payload['userType']);
        $this->assertSame(['id' => 'g-1', 'name' => 'editors'], $payload['group']);
        $this->assertArrayNotHasKey('user', $payload);
    }

    public function testSerializeWildcardAceDoesNotResolveAnyone(): void
    {
        $ace = $this->createAce(AccessControlEntryInterface::TYPE_USER_VALUE, null);
        $ace->setObjectId(null);

        $this->userRepository->expects($this->never())->method('getUser');
        $this->groupRepository->expects($this->never())->method('getGroup');

        $payload = $this->serializer->serialize($ace);

        $this->assertNull($payload['userId']);
        $this->assertNull($payload['objectId']);
        $this->assertNull($payload['parentId']);
        $this->assertSame([], $payload['metadata']);
        $this->assertArrayNotHasKey('user', $payload);
        $this->assertArrayNotHasKey('group', $payload);
    }

    public function testUnknownUserIsSerializedAsNull(): void
    {
        $ace = $this->createAce(AccessControlEntryInterface::TYPE_USER_VALUE, 'ghost');

        $this->userRepository->method('getUser')->willReturn(null);

        $payload = $this->serializer->serialize($ace);

        $this->assertArrayHasKey('user', $payload);
        $this->assertNull($payload['user']);
    }

    private function createAce(int $userType, ?string $userId): AccessControlEntry
    {
        $ace = new AccessControlEntry();
        $ace->setUserType($userType);
        $ace->setUserId($userId);
        $ace->setObjectType('publication');
        $ace->setObjectId('pub-42');
        $ace->setMask(PermissionInterface::VIEW | PermissionInterface::EDIT);

        return $ace;
    }
}
