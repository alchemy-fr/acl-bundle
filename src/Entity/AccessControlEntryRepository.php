<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Entity;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;

class AccessControlEntryRepository extends EntityRepository
{
    public static function joinAcl(
        QueryBuilder $queryBuilder,
        string $userId,
        array $groupIds,
        string $objectType,
        string $objectTableAlias,
        int $permission,
        bool $inner = true,
        string $aceAlias = 'ace',
    ): void {
        $hasGroups = !empty($groupIds);

        $method = $inner ? 'innerJoin' : 'leftJoin';

        $queryBuilder
            ->$method(
                AccessControlEntry::class,
                $aceAlias,
                Join::WITH,
                sprintf(
                    '%1$s.objectType = :ot AND (%1$s.objectId = %2$s.id OR %1$s.objectId IS NULL) AND BIT_AND(%1$s.mask, :perm) = :perm'
                        .' AND (%1$s.userId IS NULL OR (%1$s.userType = :uty AND %1$s.userId = :uid)'
                        .($hasGroups ? ' OR (%1$s.userType = :gty AND %1$s.userId IN (:gids))' : '')
                        .')',
                    $aceAlias,
                    $objectTableAlias
                )
            )
            ->setParameter('uty', AccessControlEntry::TYPE_USER_VALUE)
            ->setParameter('ot', $objectType)
            ->setParameter('uid', $userId)
            ->setParameter('perm', $permission)
        ;

        if ($hasGroups) {
            $queryBuilder
                ->setParameter('gty', AccessControlEntry::TYPE_GROUP_VALUE)
                ->setParameter('gids', $groupIds);
        }
    }

    public function getAces(string $userId, array $groupIds, string $objectType, ?string $objectId): array
    {
        $queryBuilder = $this
            ->createBaseQueryBuilder()
            ->andWhere('a.objectType = :ot')
            ->setParameter('ot', $objectType);

        $userWhere = [
            'a.userType = :ut AND a.userId = :uid OR a.userId IS NULL',
        ];

        if (!empty($groupIds)) {
            $userWhere[] = 'a.userType = :gt AND a.userId IN (:gids)';
            $queryBuilder
                ->setParameter('gt', AccessControlEntry::TYPE_GROUP_VALUE)
                ->setParameter('gids', $groupIds)
            ;
        }

        $queryBuilder
            ->andWhere($queryBuilder->expr()->orX(...$userWhere))
            ->setParameter('ut', AccessControlEntry::TYPE_USER_VALUE)
            ->setParameter('uid', $userId)
        ;

        if (null !== $objectId) {
            $queryBuilder
                ->andWhere('a.objectId = :oid OR a.objectId IS NULL')
                ->setParameter('oid', $objectId);
        } else {
            $queryBuilder->andWhere('a.objectId IS NULL');
        }

        $queryBuilder->addOrderBy('a.createdAt', 'ASC');

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    private function applyParams(QueryBuilder $queryBuilder, array $params = []): void
    {
        foreach ([
            'objectType' => 'ot',
            'userType' => 'ut',
            'objectId' => 'oid',
            'userId' => 'uid',
            'parentId' => 'pid',
        ] as $col => $alias) {
            if (isset($params[$col])) {
                $queryBuilder
                    ->andWhere(sprintf('a.%s = :%s', $col, $alias))
                    ->setParameter($alias, $params[$col]);
            }
        }
        foreach ([
            'objectId' => 'oid',
            'userId' => 'uid',
            'parentId' => 'pid',
        ] as $col => $alias) {
            if (array_key_exists($col, $params) && null === $params[$col]) {
                $queryBuilder->andWhere(sprintf('a.%s IS NULL', $col));
            }
        }

        if (isset($params['permission'])) {
            $queryBuilder
                ->andWhere('BIT_AND(a.mask, :p) = :p')
                ->setParameter('p', $params['permission']);
        }
    }

    public function findAcesByParams(array $params = []): array
    {
        $queryBuilder = $this->createBaseQueryBuilder();
        $this->applyParams($queryBuilder, $params);

        $queryBuilder->addOrderBy('a.parentId', 'ASC');
        $queryBuilder->addOrderBy('a.createdAt', 'ASC');

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    public function deleteAcesByParams(array $params = []): void
    {
        $queryBuilder = $this->createQueryBuilder('a')
            ->delete();

        $this->applyParams($queryBuilder, $params);

        $queryBuilder
            ->getQuery()
            ->execute();
    }

    public function getAllowedUserIds(string $objectType, string $objectId, int $permission): array
    {
        return $this->getAllowedIds(AccessControlEntry::TYPE_USER_VALUE, $objectType, $objectId, $permission);
    }

    public function getAllowedGroupIds(string $objectType, string $objectId, int $permission): array
    {
        return $this->getAllowedIds(AccessControlEntry::TYPE_GROUP_VALUE, $objectType, $objectId, $permission);
    }

    private function getAllowedIds(int $userType, string $objectType, string $objectId, int $permission): array
    {
        return array_map(fn (array $row): ?string => $row['userId'], $this
            ->createBaseQueryBuilder()
            ->select('DISTINCT a.userId')
            ->andWhere('a.objectType = :ot')
            ->andWhere('a.objectId = :oid OR a.objectId IS NULL')
            ->andWhere('BIT_AND(a.mask, :p) = :p')
            ->andWhere('a.userType = :ut')
            ->setParameter('ut', $userType)
            ->setParameter('ot', $objectType)
            ->setParameter('oid', $objectId)
            ->setParameter('p', $permission)
            ->getQuery()
            ->getScalarResult()
        );
    }

    private function createBaseQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('a');
    }
}
