<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Event;

class AclUpsertEvent extends AclEvent
{
    public const NAME = 'acl.upsert';

    private int $permissions;

    public function __construct(int $userType, ?string $userId, string $objectType, ?string $objectId, int $permissions)
    {
        $this->permissions = $permissions;
        parent::__construct($userType, $userId, $objectType, $objectId);
    }

    public function getPermissions(): int
    {
        return $this->permissions;
    }
}
