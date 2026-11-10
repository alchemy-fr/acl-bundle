<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Tests\Admin;

use Alchemy\AclBundle\Admin\PermissionView;
use Alchemy\AclBundle\Entity\AccessControlEntry;
use Alchemy\AclBundle\Mapping\ObjectMapping;
use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AclBundle\Repository\GroupRepositoryInterface;
use Alchemy\AclBundle\Repository\PermissionRepositoryInterface;
use Alchemy\AclBundle\Repository\UserRepositoryInterface;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AclBundle\Tests\Mock\ObjectMock;
use Alchemy\AclBundle\Tests\Mock\TitledObjectMock;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PermissionViewTest extends TestCase
{
    private PermissionRepositoryInterface&MockObject $repository;
    private UserRepositoryInterface&MockObject $userRepository;
    private GroupRepositoryInterface&MockObject $groupRepository;
    private EntityManagerInterface&MockObject $em;
    private PermissionView $view;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PermissionRepositoryInterface::class);

        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->userRepository->method('getUsers')->willReturn([
            ['id' => 'u-1', 'username' => 'alice'],
            ['id' => 'u-2', 'username' => 'bob'],
        ]);

        $this->groupRepository = $this->createMock(GroupRepositoryInterface::class);
        $this->groupRepository->method('getGroups')->willReturn([
            ['id' => 'g-1', 'name' => 'editors'],
        ]);

        $this->em = $this->createMock(EntityManagerInterface::class);

        $this->view = $this->createView(['VIEW', 'EDIT']);
    }

    public function testViewParametersForAnObject(): void
    {
        $typeWideAce = $this->createAce(AccessControlEntryInterface::TYPE_USER_VALUE, null, null, PermissionInterface::VIEW);
        $userAce = $this->createAce(AccessControlEntryInterface::TYPE_USER_VALUE, 'u-1', '42', PermissionInterface::VIEW | PermissionInterface::EDIT);
        $groupAce = $this->createAce(AccessControlEntryInterface::TYPE_GROUP_VALUE, 'g-1', '42', PermissionInterface::EDIT | PermissionInterface::DELETE);
        $groupAce->setParentId('p-1');

        $this->repository
            ->expects($this->exactly(2))
            ->method('getObjectAces')
            ->willReturnMap([
                ['pub', null, [$typeWideAce]],
                ['pub', '42', [$userAce, $groupAce]],
            ]);

        $objectRepository = $this->createMock(EntityRepository::class);
        $objectRepository->method('find')->with('42')->willReturn(new TitledObjectMock('42'));
        $this->em->method('getRepository')->with(TitledObjectMock::class)->willReturn($objectRepository);

        $params = $this->view->getViewParameters('pub', '42');

        $this->assertSame(['VIEW' => PermissionInterface::VIEW, 'EDIT' => PermissionInterface::EDIT], $params['permissions']);
        $this->assertSame([
            AccessControlEntryInterface::USER_WILDCARD => 'All users',
            'u-1' => 'alice',
            'u-2' => 'bob',
        ], $params['users']);
        $this->assertSame(['g-1' => 'editors'], $params['groups']);
        $this->assertSame('pub', $params['object_type']);
        $this->assertSame('42', $params['object_id']);
        $this->assertSame('Publication #42', $params['object_title']);

        $this->assertSame([
            [
                'userType' => 'user',
                'userId' => AccessControlEntryInterface::USER_WILDCARD,
                'parentId' => null,
                'name' => 'All users',
                'wildcard' => true,
                'inherited' => true,
                'mask' => PermissionInterface::VIEW,
                'permissions' => ['VIEW' => true, 'EDIT' => false],
            ],
            [
                'userType' => 'user',
                'userId' => 'u-1',
                'parentId' => null,
                'name' => 'alice',
                'wildcard' => false,
                'inherited' => false,
                'mask' => PermissionInterface::VIEW | PermissionInterface::EDIT,
                'permissions' => ['VIEW' => true, 'EDIT' => true],
            ],
            [
                'userType' => 'group',
                'userId' => 'g-1',
                'parentId' => 'p-1',
                'name' => 'editors',
                'wildcard' => false,
                'inherited' => false,
                'mask' => PermissionInterface::EDIT | PermissionInterface::DELETE,
                'permissions' => ['VIEW' => false, 'EDIT' => true],
            ],
        ], $params['aces']);
    }

    public function testViewParametersForAWholeType(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('getObjectAces')
            ->with('pub', null)
            ->willReturn([
                $this->createAce(AccessControlEntryInterface::TYPE_GROUP_VALUE, 'g-1', null, PermissionInterface::VIEW),
            ]);
        $this->em->expects($this->never())->method('getRepository');

        $params = $this->view->getViewParameters('pub', null);

        $this->assertNull($params['object_id']);
        $this->assertNull($params['object_title']);
        $this->assertCount(1, $params['aces']);
        $this->assertFalse($params['aces'][0]['inherited']);
    }

    public function testEveryPermissionIsShownWhenNoneIsEnabled(): void
    {
        $this->repository->method('getObjectAces')->willReturn([]);

        $this->assertSame(
            PermissionInterface::PERMISSIONS,
            $this->createView(null)->getViewParameters('pub', null)['permissions']
        );
    }

    public function testObjectTitleIsNullWhenTheObjectIsNotStringable(): void
    {
        $this->repository->method('getObjectAces')->willReturn([]);

        $objectRepository = $this->createMock(EntityRepository::class);
        $objectRepository->method('find')->willReturn(new ObjectMock('42'));
        $this->em->method('getRepository')->willReturn($objectRepository);

        $this->assertNull($this->view->getViewParameters('pub', '42')['object_title']);
    }

    public function testObjectTitleIsNullWhenTheObjectIsMissing(): void
    {
        $this->repository->method('getObjectAces')->willReturn([]);

        $objectRepository = $this->createMock(EntityRepository::class);
        $objectRepository->method('find')->willReturn(null);
        $this->em->method('getRepository')->willReturn($objectRepository);

        $this->assertNull($this->view->getViewParameters('pub', '42')['object_title']);
    }

    public function testNamesMissingFromTheListsAreLookedUp(): void
    {
        $this->repository->method('getObjectAces')->willReturn([
            $this->createAce(AccessControlEntryInterface::TYPE_USER_VALUE, 'u-1', null, PermissionInterface::VIEW),
            $this->createAce(AccessControlEntryInterface::TYPE_USER_VALUE, 'u-3', null, PermissionInterface::VIEW),
            $this->createAce(AccessControlEntryInterface::TYPE_GROUP_VALUE, 'g-2', null, PermissionInterface::VIEW),
        ]);
        $this->userRepository
            ->expects($this->once())
            ->method('getUser')
            ->with('u-3')
            ->willReturn(['id' => 'u-3', 'username' => 'carol']);
        $this->groupRepository
            ->expects($this->once())
            ->method('getGroup')
            ->with('g-2')
            ->willReturn(['id' => 'g-2', 'name' => 'viewers']);

        $aces = $this->view->getViewParameters('pub', null)['aces'];

        $this->assertSame(['alice', 'carol', 'viewers'], array_column($aces, 'name'));
    }

    public function testUnknownUsersAndGroupsGetAPlaceholderName(): void
    {
        $this->repository->method('getObjectAces')->willReturn([
            $this->createAce(AccessControlEntryInterface::TYPE_USER_VALUE, 'ghost', null, PermissionInterface::VIEW),
            $this->createAce(AccessControlEntryInterface::TYPE_GROUP_VALUE, 'nobody', null, PermissionInterface::VIEW),
        ]);

        $aces = $this->view->getViewParameters('pub', null)['aces'];

        $this->assertSame('User "ghost" not found', $aces[0]['name']);
        $this->assertSame('Group "nobody" not found', $aces[1]['name']);
    }

    private function createView(?array $enabledPermissions): PermissionView
    {
        return new PermissionView(
            new ObjectMapping(['pub' => TitledObjectMock::class]),
            $this->repository,
            $this->userRepository,
            $this->groupRepository,
            $this->em,
            $enabledPermissions,
        );
    }

    private function createAce(int $userType, ?string $userId, ?string $objectId, int $mask): AccessControlEntry
    {
        $ace = new AccessControlEntry();
        $ace->setUserType($userType);
        $ace->setUserId($userId);
        $ace->setObjectType('pub');
        $ace->setObjectId($objectId);
        $ace->setMask($mask);

        return $ace;
    }
}
