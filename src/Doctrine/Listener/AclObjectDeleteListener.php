<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Doctrine\Listener;

use Alchemy\AclBundle\AclObjectInterface;
use Alchemy\AclBundle\Mapping\ObjectMapping;
use Alchemy\AclBundle\Repository\PermissionRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;

/**
 * The object id is read on preRemove: Doctrine resets the identifier of entities
 * with a generated id before dispatching postRemove.
 */
#[AsDoctrineListener(Events::preRemove)]
#[AsDoctrineListener(Events::postRemove)]
final class AclObjectDeleteListener
{
    /**
     * @var array<int, array{objectType: string, objectId: string}>
     */
    private array $pendingDeletions = [];

    public function __construct(
        private readonly ObjectMapping $objectMapping,
        private readonly PermissionRepositoryInterface $permissionRepository,
    ) {
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $object = $args->getObject();
        if (!$object instanceof AclObjectInterface || !$this->objectMapping->isObjectMapped($object)) {
            return;
        }

        $this->pendingDeletions[spl_object_id($object)] = [
            'objectType' => $this->objectMapping->getObjectKey($object),
            'objectId' => $object->getId(),
        ];
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $key = spl_object_id($args->getObject());
        if (!isset($this->pendingDeletions[$key])) {
            return;
        }

        $params = $this->pendingDeletions[$key];
        unset($this->pendingDeletions[$key]);

        $this->permissionRepository->deleteAcesByParams($params);
    }
}
