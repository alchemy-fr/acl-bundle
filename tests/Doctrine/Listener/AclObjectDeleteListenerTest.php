<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Tests\Doctrine\Listener;

use Alchemy\AclBundle\Doctrine\Listener\AclObjectDeleteListener;
use Alchemy\AclBundle\Mapping\ObjectMapping;
use Alchemy\AclBundle\Repository\PermissionRepositoryInterface;
use Alchemy\AclBundle\Tests\Mock\ChildObjectMock;
use Alchemy\AclBundle\Tests\Mock\ObjectMock;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostRemoveEventArgs;
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

        $listener->postRemove($this->createEvent(new ChildObjectMock('42')));
    }

    public function testNonAclObjectsAreIgnored(): void
    {
        $repository = $this->createMock(PermissionRepositoryInterface::class);
        $repository->expects($this->never())->method('deleteAcesByParams');

        $listener = new AclObjectDeleteListener(new ObjectMapping(['pub' => ObjectMock::class]), $repository);

        $listener->postRemove($this->createEvent(new \stdClass()));
    }

    private function createEvent(object $object): PostRemoveEventArgs
    {
        return new PostRemoveEventArgs($object, $this->createMock(EntityManagerInterface::class));
    }
}
