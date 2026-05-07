<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Event;

class AclUpsertEvent extends AclEvent
{
    public const NAME = 'acl.upsert';

    public function __construct(
        int $userType,
        ?string $userId,
        string $objectType,
        ?string $objectId,
        private readonly int $permissions,
        private readonly array $metadata,
        private readonly ?int $previousPermissions,
        private readonly ?array $previousMetadata,
    )
    {
        parent::__construct($userType, $userId, $objectType, $objectId);
    }

    public function getPermissions(): int
    {
        return $this->permissions;
    }

    public function getPreviousPermissions(): ?int
    {
        return $this->previousPermissions;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getPreviousMetadata(): ?array
    {
        return $this->previousMetadata;
    }
}
