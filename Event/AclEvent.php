<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

abstract class AclEvent extends Event
{
    protected int $userType;
    protected ?string $userId;
    protected string $objectType;
    protected ?string $objectId;

    public function __construct(int $userType, ?string $userId, string $objectType, ?string $objectId)
    {
        $this->userType = $userType;
        $this->userId = $userId;
        $this->objectType = $objectType;
        $this->objectId = $objectId;
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

    public function setObjectType(string $objectType): void
    {
        $this->objectType = $objectType;
    }

    public function getObjectId(): ?string
    {
        return $this->objectId;
    }

    public function setObjectId(?string $objectId): void
    {
        $this->objectId = $objectId;
    }
}
