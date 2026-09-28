<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Tests\Security;

use Alchemy\AclBundle\Entity\AccessControlEntry;
use Alchemy\AclBundle\Event\AclDeleteEvent;
use Alchemy\AclBundle\Event\AclUpsertEvent;
use Alchemy\AclBundle\Mapping\ObjectMapping;
use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AclBundle\Repository\PermissionRepositoryInterface;
use Alchemy\AclBundle\Repository\UserRepositoryInterface;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AclBundle\Security\PermissionManager;
use Alchemy\AclBundle\Tests\Mock\AclUserMock;
use Alchemy\AclBundle\Tests\Mock\ObjectMock;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class PermissionManagerTest extends TestCase
{
    private PermissionRepositoryInterface&MockObject $repository;
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private UserRepositoryInterface&MockObject $userRepository;
    private PermissionManager $manager;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PermissionRepositoryInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->userRepository->method('getAclGroupsId')->willReturn([]);

        $this->manager = new PermissionManager(
            new ObjectMapping(['pub' => ObjectMock::class]),
            $this->repository,
            $this->eventDispatcher,
            $this->userRepository,
        );
    }

    public function testOwnerIsGrantedWithoutLookingAtAces(): void
    {
        $this->repository->expects($this->never())->method('getAces');

        $user = new AclUserMock('u-1');
        $object = new ObjectMock('42', 'u-1');

        $this->assertTrue($this->manager->isGranted($user, $object, PermissionInterface::DELETE));
    }

    public function testOwnershipGrantCanBeDisabled(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('getAces')
            ->willReturn([]);

        $user = new AclUserMock('u-1');
        $object = new ObjectMock('42', 'u-1');

        $this->assertFalse($this->manager->isGranted($user, $object, PermissionInterface::DELETE, false));
    }

    public function testIsGrantedWithSeveralPermissionsMatchesAnyOfThem(): void
    {
        $this->repository
            ->method('getAces')
            ->willReturn([$this->createAce(PermissionInterface::EDIT)]);

        $user = new AclUserMock('u-1');
        $object = new ObjectMock('42');

        $this->assertTrue($this->manager->isGranted($user, $object, [PermissionInterface::VIEW, PermissionInterface::EDIT]));
        $this->assertFalse($this->manager->isGranted($user, $object, [PermissionInterface::VIEW, PermissionInterface::DELETE]));
    }

    public function testIsGrantedIgnoresNullAces(): void
    {
        $this->repository
            ->method('getAces')
            ->willReturn([null, $this->createAce(PermissionInterface::VIEW)]);

        $this->assertTrue($this->manager->isGranted(new AclUserMock('u-1'), new ObjectMock('42'), PermissionInterface::VIEW));
    }

    public function testGetAcesQueriesTheRepositoryWithTheUserGroups(): void
    {
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $user = new AclUserMock('u-1');
        $userRepository
            ->expects($this->once())
            ->method('getAclGroupsId')
            ->with($user)
            ->willReturn(['g-1', 'g-2']);

        $aces = [$this->createAce(PermissionInterface::VIEW)];
        $this->repository
            ->expects($this->once())
            ->method('getAces')
            ->with('u-1', ['g-1', 'g-2'], 'pub', '42')
            ->willReturn($aces);

        $manager = new PermissionManager(
            new ObjectMapping(['pub' => ObjectMock::class]),
            $this->repository,
            $this->eventDispatcher,
            $userRepository,
        );

        $this->assertSame($aces, $manager->getAces($user, new ObjectMock('42')));
    }

    public function testGetAcesIsCachedPerUserAndObject(): void
    {
        $this->repository
            ->expects($this->exactly(3))
            ->method('getAces')
            ->willReturn([]);

        $user = new AclUserMock('u-1');
        $object = new ObjectMock('42');

        $this->manager->getAces($user, $object);
        $this->manager->getAces($user, $object);
        $this->manager->getAces($user, new ObjectMock('43'));
        $this->manager->getAces(new AclUserMock('u-2'), $object);
    }

    public function testResetCacheForcesANewQuery(): void
    {
        $this->repository
            ->expects($this->exactly(2))
            ->method('getAces')
            ->willReturn([]);

        $user = new AclUserMock('u-1');
        $object = new ObjectMock('42');

        $this->manager->getAces($user, $object);
        $this->manager->resetCache();
        $this->manager->getAces($user, $object);
    }

    public function testGetObjectAcesMergesObjectAndTypeWideAces(): void
    {
        $objectAce = $this->createAce(PermissionInterface::VIEW);
        $typeAce = $this->createAce(PermissionInterface::EDIT);

        $this->repository
            ->expects($this->exactly(2))
            ->method('getObjectAces')
            ->willReturnMap([
                ['pub', '42', [$objectAce]],
                ['pub', null, [$typeAce]],
            ]);

        $this->assertSame([$objectAce, $typeAce], $this->manager->getObjectAces(new ObjectMock('42')));
    }

    public function testGetAllowedUsersAndGroups(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('getAllowedUserIds')
            ->with('pub', '42', PermissionInterface::VIEW)
            ->willReturn(['u-1', null]);
        $this->repository
            ->expects($this->once())
            ->method('getAllowedGroupIds')
            ->with('pub', '42', PermissionInterface::EDIT)
            ->willReturn(['g-1']);

        $object = new ObjectMock('42');

        $this->assertSame(['u-1', null], $this->manager->getAllowedUsers($object, PermissionInterface::VIEW));
        $this->assertSame(['g-1'], $this->manager->getAllowedGroups($object, PermissionInterface::EDIT));
    }

    public function testGrantUserOnObject(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('updateOrCreateAce')
            ->with(
                AccessControlEntryInterface::TYPE_USER_VALUE,
                'u-1',
                'pub',
                '42',
                PermissionInterface::EDIT,
                [],
                'p:1',
                false,
            )
            ->willReturn($this->createAce(PermissionInterface::EDIT));

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(AclUpsertEvent::class), AclUpsertEvent::NAME);

        $this->manager->grantUserOnObject('u-1', new ObjectMock('42'), PermissionInterface::EDIT, 'p:1');
    }

    public function testGrantGroupOnObject(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('updateOrCreateAce')
            ->with(
                AccessControlEntryInterface::TYPE_GROUP_VALUE,
                'g-1',
                'pub',
                '42',
                PermissionInterface::VIEW,
                [],
                null,
                false,
            )
            ->willReturn($this->createAce(PermissionInterface::VIEW));

        $this->manager->grantGroupOnObject('g-1', new ObjectMock('42'), PermissionInterface::VIEW);
    }

    public function testUpdateOrCreateAceDispatchesAnUpsertEventWithThePreviousState(): void
    {
        $previous = $this->createAce(PermissionInterface::VIEW);
        $previous->setMetadata(['v' => 1]);
        $updated = $this->createAce(PermissionInterface::VIEW | PermissionInterface::EDIT);

        $this->repository
            ->expects($this->once())
            ->method('updateOrCreateAce')
            ->willReturnCallback(function (int $userType, ?string $userId, string $objectType, ?string $objectId, int $mask, array $metadata, ?string $parentId, bool $append, &$previousAce) use ($previous, $updated) {
                $previousAce = $previous;

                return $updated;
            });

        $dispatched = null;
        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(AclUpsertEvent::class), AclUpsertEvent::NAME)
            ->willReturnCallback(function (AclUpsertEvent $event) use (&$dispatched) {
                $dispatched = $event;

                return $event;
            });

        $result = $this->manager->updateOrCreateAce(
            AccessControlEntryInterface::TYPE_USER_VALUE,
            'u-1',
            'pub',
            '42',
            PermissionInterface::VIEW | PermissionInterface::EDIT,
            ['v' => 2],
        );

        $this->assertSame($updated, $result);
        $this->assertInstanceOf(AclUpsertEvent::class, $dispatched);
        $this->assertSame(AccessControlEntryInterface::TYPE_USER_VALUE, $dispatched->getUserType());
        $this->assertSame('u-1', $dispatched->getUserId());
        $this->assertSame('pub', $dispatched->getObjectType());
        $this->assertSame('42', $dispatched->getObjectId());
        $this->assertSame(PermissionInterface::VIEW | PermissionInterface::EDIT, $dispatched->getPermissions());
        $this->assertSame(['v' => 2], $dispatched->getMetadata());
        $this->assertSame(PermissionInterface::VIEW, $dispatched->getPreviousPermissions());
        $this->assertSame(['v' => 1], $dispatched->getPreviousMetadata());
    }

    public function testUpdateOrCreateAceOfANewAceHasNoPreviousState(): void
    {
        $this->repository
            ->method('updateOrCreateAce')
            ->willReturn($this->createAce(PermissionInterface::VIEW));

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (AclUpsertEvent $event): bool {
                $this->assertNull($event->getPreviousPermissions());
                $this->assertNull($event->getPreviousMetadata());

                return true;
            }), AclUpsertEvent::NAME);

        $this->manager->updateOrCreateAce(AccessControlEntryInterface::TYPE_USER_VALUE, 'u-1', 'pub', null, PermissionInterface::VIEW);
    }

    public function testUpdateOrCreateAceInvalidatesTheCache(): void
    {
        $this->repository
            ->expects($this->exactly(2))
            ->method('getAces')
            ->willReturn([]);
        $this->repository
            ->method('updateOrCreateAce')
            ->willReturn($this->createAce(PermissionInterface::VIEW));

        $user = new AclUserMock('u-1');
        $object = new ObjectMock('42');

        $this->manager->getAces($user, $object);
        $this->manager->updateOrCreateAce(AccessControlEntryInterface::TYPE_USER_VALUE, 'u-1', 'pub', '42', PermissionInterface::VIEW);
        $this->manager->getAces($user, $object);
    }

    public function testDeleteAceDispatchesADeleteEventWithThePreviousState(): void
    {
        $previous = $this->createAce(PermissionInterface::VIEW | PermissionInterface::DELETE);
        $previous->setMetadata(['v' => 1]);

        $this->repository
            ->expects($this->once())
            ->method('deleteAce')
            ->willReturnCallback(function (int $userType, ?string $userId, string $objectType, ?string $objectId, ?string $parentId, &$previousAce) use ($previous): bool {
                $this->assertSame(AccessControlEntryInterface::TYPE_GROUP_VALUE, $userType);
                $this->assertSame('g-1', $userId);
                $this->assertSame('pub', $objectType);
                $this->assertSame('42', $objectId);
                $this->assertSame('p:1', $parentId);
                $previousAce = $previous;

                return true;
            });

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (AclDeleteEvent $event): bool {
                $this->assertSame(AccessControlEntryInterface::TYPE_GROUP_VALUE, $event->getUserType());
                $this->assertSame('g-1', $event->getUserId());
                $this->assertSame('pub', $event->getObjectType());
                $this->assertSame('42', $event->getObjectId());
                $this->assertSame(PermissionInterface::VIEW | PermissionInterface::DELETE, $event->getPreviousPermissions());
                $this->assertSame(['v' => 1], $event->getPreviousMetadata());

                return true;
            }), AclDeleteEvent::NAME);

        $this->manager->deleteAce(AccessControlEntryInterface::TYPE_GROUP_VALUE, 'g-1', 'pub', '42', 'p:1');
    }

    public function testDeleteAceDoesNotDispatchWhenNothingWasDeleted(): void
    {
        $this->repository->method('deleteAce')->willReturn(false);
        $this->eventDispatcher->expects($this->never())->method('dispatch');

        $this->manager->deleteAce(AccessControlEntryInterface::TYPE_USER_VALUE, 'u-1', 'pub', '42');
    }

    public function testDeleteAceInvalidatesTheCache(): void
    {
        $this->repository
            ->expects($this->exactly(2))
            ->method('getAces')
            ->willReturn([]);
        $this->repository->method('deleteAce')->willReturn(false);

        $user = new AclUserMock('u-1');
        $object = new ObjectMock('42');

        $this->manager->getAces($user, $object);
        $this->manager->deleteAce(AccessControlEntryInterface::TYPE_USER_VALUE, 'u-1', 'pub', '42');
        $this->manager->getAces($user, $object);
    }

    public function testFindAceAndFindAcesDelegateToTheRepository(): void
    {
        $ace = $this->createAce(PermissionInterface::VIEW);

        $this->repository
            ->expects($this->once())
            ->method('findAce')
            ->with(AccessControlEntryInterface::TYPE_USER_VALUE, 'u-1', 'pub', '42', 'p:1')
            ->willReturn($ace);
        $this->repository
            ->expects($this->once())
            ->method('findAces')
            ->with(AccessControlEntryInterface::TYPE_USER_VALUE, null, 'pub', '42')
            ->willReturn([$ace]);

        $this->assertSame($ace, $this->manager->findAce(AccessControlEntryInterface::TYPE_USER_VALUE, 'u-1', 'pub', '42', 'p:1'));
        $this->assertSame([$ace], $this->manager->findAces(AccessControlEntryInterface::TYPE_USER_VALUE, null, 'pub', '42'));
    }

    public function testUnmappedObjectIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->manager->isGranted(new AclUserMock('u-1'), new class implements \Alchemy\AclBundle\AclObjectInterface {
            public function getId(): string
            {
                return 'x';
            }

            public function getAclOwnerId(): string
            {
                return '';
            }
        }, PermissionInterface::VIEW);
    }

    private function createAce(int $mask): AccessControlEntry
    {
        $ace = new AccessControlEntry();
        $ace->setObjectType('pub');
        $ace->setObjectId('42');
        $ace->setUserId('u-1');
        $ace->setMask($mask);

        return $ace;
    }
}
