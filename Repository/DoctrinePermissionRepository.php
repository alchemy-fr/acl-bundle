<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Repository;

use Alchemy\AclBundle\Entity\AccessControlEntry;
use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Doctrine\ORM\EntityManagerInterface;

class DoctrinePermissionRepository implements PermissionRepositoryInterface
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function getObjectAces(string $objectType, ?string $objectId): array
    {
        return $this->em->getRepository(AccessControlEntry::class)
            ->findBy([
                'objectType' => $objectType,
                'objectId' => $objectId,
            ], [
                'parentId' => 'DESC',
                'createdAt' => 'DESC',
            ]);
    }

    public function findAcesByParams(array $params = []): array
    {
        return $this->em
            ->getRepository(AccessControlEntry::class)
            ->findAcesByParams($params);
    }

    public function deleteAcesByParams(array $params = []): void
    {
        $this->em
            ->getRepository(AccessControlEntry::class)
            ->deleteAcesByParams($params);
    }

    public function getAces(string $userId, array $groupIds, string $objectType, ?string $objectId): array
    {
        return $this->em
            ->getRepository(AccessControlEntry::class)
            ->getAces($userId, $groupIds, $objectType, $objectId);
    }

    public function getAllowedUserIds(string $objectType, string $objectId, int $permission): array
    {
        return $this->em
            ->getRepository(AccessControlEntry::class)
            ->getAllowedUserIds($objectType, $objectId, $permission);
    }

    public function getAllowedGroupIds(string $objectType, string $objectId, int $permission): array
    {
        return $this->em
            ->getRepository(AccessControlEntry::class)
            ->getAllowedGroupIds($objectType, $objectId, $permission);
    }

    public function findAce(
        int $userType,
        ?string $userId,
        string $objectType,
        ?string $objectId,
        string $parentId = null,
    ): ?AccessControlEntryInterface {
        if (null !== $objectId && empty($objectId)) {
            throw new \InvalidArgumentException('Empty objectId');
        }

        $userId = AccessControlEntryInterface::USER_WILDCARD === $userId ? null : $userId;

        return $this->em->getRepository(AccessControlEntry::class)
            ->findOneBy([
                'objectType' => $objectType,
                'objectId' => $objectId,
                'userType' => $userType,
                'userId' => $userId,
                'parentId' => $parentId,
            ]);

    }

    public function findAces(
        int $userType,
        ?string $userId,
        string $objectType,
        ?string $objectId,
    ): array {
        if (null !== $objectId && empty($objectId)) {
            throw new \InvalidArgumentException('Empty objectId');
        }

        $userId = AccessControlEntryInterface::USER_WILDCARD === $userId ? null : $userId;

        return $this->em->getRepository(AccessControlEntry::class)
            ->findBy([
                'objectType' => $objectType,
                'objectId' => $objectId,
                'userType' => $userType,
                'userId' => $userId,
            ], [
                'parentId' => 'ASC',
            ]);
    }

    public function updateOrCreateAce(
        int $userType,
        ?string $userId,
        string $objectType,
        ?string $objectId,
        int $mask,
        string $parentId = null,
        bool $append = false
    ): AccessControlEntryInterface {
        $ace = $this->findAce($userType, $userId, $objectType, $objectId, $parentId);

        if (!$ace instanceof AccessControlEntry) {
            $userId = AccessControlEntryInterface::USER_WILDCARD === $userId ? null : $userId;
            $ace = new AccessControlEntry();
            $ace->setUserType($userType);
            $ace->setUserId($userId);
            $ace->setObjectType($objectType);
            $ace->setObjectId($objectId);
            $ace->setParentId($parentId);
        }

        if ($append) {
            $ace->addPermission($mask);
        } else {
            $ace->setMask($mask);
        }

        $this->em->persist($ace);
        $this->em->flush();

        return $ace;
    }

    public function deleteAce(
        int $userType,
        ?string $userId,
        string $objectType,
        ?string $objectId,
        string $parentId = null,
    ): bool {
        $userId = AccessControlEntryInterface::USER_WILDCARD === $userId ? null : $userId;

        $ace = $this->em->getRepository(AccessControlEntry::class)
            ->findOneBy([
                'objectType' => $objectType,
                'objectId' => $objectId,
                'userType' => $userType,
                'userId' => $userId,
                'parentId' => $parentId,
            ]);

        if ($ace instanceof AccessControlEntry) {
            $this->em->remove($ace);
            $this->em->flush();

            return true;
        }

        return false;
    }
}
