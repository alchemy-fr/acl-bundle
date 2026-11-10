<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Tests\Doctrine\Listener;

use Alchemy\AclBundle\Doctrine\Listener\AclObjectDeleteListener;
use Alchemy\AclBundle\Mapping\ObjectMapping;
use Alchemy\AclBundle\Repository\PermissionRepositoryInterface;
use Alchemy\AclBundle\Tests\Mock\ChildObjectMock;
use Alchemy\AclBundle\Tests\Mock\GeneratedIdObjectMock;
use Alchemy\AclBundle\Tests\Mock\ObjectMock;
use Alchemy\AclBundle\Tests\Mock\UnmappedObjectMock;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use PHPUnit\Framework\TestCase;

class AclObjectDeleteListenerTest extends TestCase
{
    public function testAcesAreDeletedWhenAMappedObjectIsRemoved(): void
    {
        $repository = $this->createMock(PermissionRepositoryInterface::class);
        $repository
            ->expects($this->once())
            ->method('deleteAcesByParams')
            ->with([
                'objectType' => 'pub',
                'objectId' => '42',
            ]);

        $listener = new AclObjectDeleteListener(new ObjectMapping(['pub' => ObjectMock::class]), $repository);

        $this->remove($listener, new ChildObjectMock('42'));
    }

    public function testTheIdIsReadBeforeDoctrineResetsIt(): void
    {
        $repository = $this->createMock(PermissionRepositoryInterface::class);
        $repository
            ->expects($this->once())
            ->method('deleteAcesByParams')
            ->with([
                'objectType' => 'pub',
                'objectId' => '42',
            ]);

        $listener = new AclObjectDeleteListener(new ObjectMapping(['pub' => GeneratedIdObjectMock::class]), $repository);

        $object = new GeneratedIdObjectMock('42');
        $listener->preRemove(new PreRemoveEventArgs($object, $this->createMock(EntityManagerInterface::class)));
        $object->id = null;
        $listener->postRemove(new PostRemoveEventArgs($object, $this->createMock(EntityManagerInterface::class)));
    }

    public function testAcesAreDeletedOnlyOnce(): void
    {
        $repository = $this->createMock(PermissionRepositoryInterface::class);
        $repository->expects($this->once())->method('deleteAcesByParams');

        $listener = new AclObjectDeleteListener(new ObjectMapping(['pub' => ObjectMock::class]), $repository);

        $object = new ObjectMock('42');
        $this->remove($listener, $object);
        $listener->postRemove(new PostRemoveEventArgs($object, $this->createMock(EntityManagerInterface::class)));
    }

    public function testACancelledRemovalDoesNotDeleteTheAcesOfAnotherObject(): void
    {
        $repository = $this->createMock(PermissionRepositoryInterface::class);
        $repository->expects($this->never())->method('deleteAcesByParams');

        $listener = new AclObjectDeleteListener(new ObjectMapping(['pub' => ObjectMock::class]), $repository);
        $em = $this->createMock(EntityManagerInterface::class);

        // Removed, then persisted again or detached: postRemove never comes for it
        $object = new ObjectMock('42');
        $listener->preRemove(new PreRemoveEventArgs($object, $em));
        $objectId = spl_object_id($object);
        unset($object);

        // Another object gets the handle PHP freed
        $others = [];
        do {
            $other = new \stdClass();
            $others[] = $other;
        } while (spl_object_id($other) !== $objectId && count($others) < 1000);
        $this->assertSame($objectId, spl_object_id($other));

        $listener->postRemove(new PostRemoveEventArgs($other, $em));
    }

    public function testNonAclObjectsAreIgnored(): void
    {
        $repository = $this->createMock(PermissionRepositoryInterface::class);
        $repository->expects($this->never())->method('deleteAcesByParams');

        $listener = new AclObjectDeleteListener(new ObjectMapping(['pub' => ObjectMock::class]), $repository);

        $this->remove($listener, new \stdClass());
    }

    public function testUnmappedAclObjectsAreIgnored(): void
    {
        $repository = $this->createMock(PermissionRepositoryInterface::class);
        $repository->expects($this->never())->method('deleteAcesByParams');

        $listener = new AclObjectDeleteListener(new ObjectMapping(['pub' => ObjectMock::class]), $repository);

        $this->remove($listener, new UnmappedObjectMock());
    }

    private function remove(AclObjectDeleteListener $listener, object $object): void
    {
        $em = $this->createMock(EntityManagerInterface::class);

        $listener->preRemove(new PreRemoveEventArgs($object, $em));
        $listener->postRemove(new PostRemoveEventArgs($object, $em));
    }
}
