<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Doctrine\Listener;

use Alchemy\AclBundle\AclObjectInterface;
use Alchemy\AclBundle\Mapping\ObjectMapping;
use Alchemy\AclBundle\Repository\PermissionRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(Events::postRemove)]
final readonly class AclObjectDeleteListener
{
    public function __construct(
        private ObjectMapping $objectMapping,
        private PermissionRepositoryInterface $permissionRepository,
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
}
