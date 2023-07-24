<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Doctrine\Listener;

use Alchemy\AclBundle\AclObjectInterface;
use Alchemy\AclBundle\Mapping\ObjectMapping;
use Alchemy\AclBundle\Repository\PermissionRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Events;

final class AclObjectDeleteListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly ObjectMapping $objectMapping,
        private readonly PermissionRepositoryInterface $permissionRepository,
    ) {
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $object = $args->getObject();
        if (!$object instanceof AclObjectInterface) {
            return;
        }

        $this->permissionRepository->deleteAcesByParams([
            'objectType' => $this->objectMapping->getObjectKey($object),
            'objectId' => $object->getId(),
        ]);
    }

    public function getSubscribedEvents()
    {
        return [
            Events::postRemove,
        ];
    }
}
