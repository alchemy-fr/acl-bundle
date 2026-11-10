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
     * Weak keys: an entry whose removal is never flushed (persisted again, detached,
     * failed flush) goes away with its object, and cannot match another object.
     *
     * @var \WeakMap<object, array{objectType: string, objectId: string}>
     */
    private readonly \WeakMap $pendingDeletions;

    public function __construct(
        private readonly ObjectMapping $objectMapping,
        private readonly PermissionRepositoryInterface $permissionRepository,
    ) {
        $this->pendingDeletions = new \WeakMap();
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $object = $args->getObject();
        if (!$object instanceof AclObjectInterface || !$this->objectMapping->isObjectMapped($object)) {
            return;
        }

        $this->pendingDeletions[$object] = [
            'objectType' => $this->objectMapping->getObjectKey($object),
            'objectId' => $object->getId(),
        ];
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $object = $args->getObject();
        if (!isset($this->pendingDeletions[$object])) {
            return;
        }

        $params = $this->pendingDeletions[$object];
        unset($this->pendingDeletions[$object]);

        $this->permissionRepository->deleteAcesByParams($params);
    }
}
