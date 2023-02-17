<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Model;

interface AccessControlEntryInterface
{
    public const USER_WILDCARD = '__ALL_USERS__';

    public const TYPE_USER_VALUE = 0;
    public const TYPE_GROUP_VALUE = 1;
    public const TYPE_USER = 'user';
    public const TYPE_GROUP = 'group';

    public const USER_TYPES = [
        self::TYPE_USER => self::TYPE_USER_VALUE,
        self::TYPE_GROUP => self::TYPE_GROUP_VALUE,
    ];

    public function getId(): string;

    public function getUserType(): int;

    public function setUserType(int $userType): void;

    public function getUserId(): ?string;

    public function setUserId(string $userId): void;

    public function getObjectType(): ?string;

    public function setObjectType(string $objectType): void;

    public function getObjectId(): ?string;

    public function setObjectId(?string $objectId): void;

    public function getMask(): int;

    public function setMask(int $mask): void;

    public function hasPermission(int $permission): bool;

    public function setPermissions(array $permissions): void;

    public function getPermissions(): array;

    public function addPermission(int $permission): void;

    public function removePermission(int $permission): void;

    public function resetPermissions(): void;
}
