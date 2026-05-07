<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

abstract class AclEvent extends Event
{
    public function __construct(
        protected readonly int $userType,
        protected readonly ?string $userId,
        protected readonly string $objectType,
        protected readonly ?string $objectId,
        protected readonly ?int $previousPermissions,
        protected readonly ?array $previousMetadata,
    )
    {
    }

    public function getUserType(): int
    {
        return $this->userType;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function getObjectType(): string
    {
        return $this->objectType;
    }

    public function getObjectId(): ?string
    {
        return $this->objectId;
    }

    public function getPreviousPermissions(): ?int
    {
        return $this->previousPermissions;
    }

    public function getPreviousMetadata(): ?array
    {
        return $this->previousMetadata;
    }
}
