<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Event;

class AclUpsertEvent extends AclEvent
{
    public const string NAME = 'acl.upsert';

    public function __construct(
        int $userType,
        ?string $userId,
        string $objectType,
        ?string $objectId,
        private readonly int $permissions,
        private readonly array $metadata,
        ?int $previousPermissions,
        ?array $previousMetadata,
    ) {
        parent::__construct($userType, $userId, $objectType, $objectId, $previousPermissions, $previousMetadata);
    }

    public function getPermissions(): int
    {
        return $this->permissions;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }
}
