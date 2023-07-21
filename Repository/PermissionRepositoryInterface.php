<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Repository;

use Alchemy\AclBundle\Model\AccessControlEntryInterface;

interface PermissionRepositoryInterface
{
    /**
     * @return AccessControlEntryInterface[]
     */
    public function findAcesByParams(array $params = []): array;

    public function getAces(string $userId, array $groupIds, string $objectType, ?string $objectId): array;

    public function getAllowedUserIds(string $objectType, string $objectId, int $permission): array;

    public function getAllowedGroupIds(string $objectType, string $objectId, int $permission): array;

    /**
     * @return AccessControlEntryInterface[]
     */
    public function getObjectAces(string $objectType, ?string $objectId): array;

    public function findAce(
        int $userType,
        ?string $userId,
        string $objectType,
        string $objectId,
        ?string $parentId = null,
    ): ?AccessControlEntryInterface;

    public function findAces(
        int $userType,
        ?string $userId,
        string $objectType,
        string $objectId,
    ): array;

    public function updateOrCreateAce(
        int $userType,
        string $userId,
        string $objectType,
        ?string $objectId,
        int $mask,
        ?string $parentId = null,
        bool $append = false
    ): AccessControlEntryInterface;

    /**
     * @return bool Whether the ACE has been deleted
     */
    public function deleteAce(
        int $userType,
        string $userId,
        string $objectType,
        ?string $objectId,
        ?string $parentId = null,
    ): bool;
}
