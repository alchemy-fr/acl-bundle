<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Tests\Entity;

use Alchemy\AclBundle\Entity\AccessControlEntry;
use Alchemy\AclBundle\Entity\AccessControlEntryRepository;
use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AclBundle\Tests\Mock\ObjectMock;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Covers the DQL produced by the static join helper. The other repository methods
 * hit the database and are not unit-testable.
 */
class AccessControlEntryRepositoryTest extends TestCase
{
    public function testJoinAclWithGroups(): void
    {
        $queryBuilder = $this->createQueryBuilder();

        AccessControlEntryRepository::joinAcl($queryBuilder, 'u-1', ['g-1', 'g-2'], 'pub', 'o', PermissionInterface::EDIT);

        $join = $this->getJoin($queryBuilder);
        $this->assertSame(Join::INNER_JOIN, $join->getJoinType());
        $this->assertSame(AccessControlEntry::class, $join->getJoin());
        $this->assertSame('ace', $join->getAlias());
        $this->assertSame(Join::WITH, $join->getConditionType());
        $this->assertSame(
            'ace.objectType = :ot'
            .' AND (ace.objectId = o.id OR ace.objectId IS NULL)'
            .' AND BIT_AND(ace.mask, :perm) = :perm'
            .' AND (ace.userId IS NULL'
            .' OR (ace.userType = :uty AND ace.userId = :uid)'
            .' OR (ace.userType = :gty AND ace.userId IN (:gids)))',
            $join->getCondition()
        );

        $this->assertSame([
            'uty' => AccessControlEntryInterface::TYPE_USER_VALUE,
            'ot' => 'pub',
            'uid' => 'u-1',
            'perm' => PermissionInterface::EDIT,
            'gty' => AccessControlEntryInterface::TYPE_GROUP_VALUE,
            'gids' => ['g-1', 'g-2'],
        ], $this->getParameters($queryBuilder));
    }

    public function testJoinAclWithoutGroupsSkipsTheGroupClause(): void
    {
        $queryBuilder = $this->createQueryBuilder();

        AccessControlEntryRepository::joinAcl($queryBuilder, 'u-1', [], 'pub', 'o', PermissionInterface::VIEW);

        $condition = $this->getJoin($queryBuilder)->getCondition();
        $this->assertStringNotContainsString(':gty', $condition);
        $this->assertStringNotContainsString(':gids', $condition);
        $this->assertStringEndsWith('OR (ace.userType = :uty AND ace.userId = :uid))', $condition);

        $parameters = $this->getParameters($queryBuilder);
        $this->assertArrayNotHasKey('gty', $parameters);
        $this->assertArrayNotHasKey('gids', $parameters);
    }

    public function testJoinAclCanUseALeftJoin(): void
    {
        $queryBuilder = $this->createQueryBuilder();

        AccessControlEntryRepository::joinAcl($queryBuilder, 'u-1', [], 'pub', 'o', PermissionInterface::VIEW, false);

        $this->assertSame(Join::LEFT_JOIN, $this->getJoin($queryBuilder)->getJoinType());
    }

    public function testJoinAclWithCustomAliasAndPrefix(): void
    {
        $queryBuilder = $this->createQueryBuilder();

        AccessControlEntryRepository::joinAcl($queryBuilder, 'u-1', ['g-1'], 'pub', 'o', PermissionInterface::VIEW, true, 'acl', 'x_');

        $join = $this->getJoin($queryBuilder);
        $this->assertSame('acl', $join->getAlias());
        $this->assertSame(
            'acl.objectType = :x_ot'
            .' AND (acl.objectId = o.id OR acl.objectId IS NULL)'
            .' AND BIT_AND(acl.mask, :x_perm) = :x_perm'
            .' AND (acl.userId IS NULL'
            .' OR (acl.userType = :x_uty AND acl.userId = :x_uid)'
            .' OR (acl.userType = :x_gty AND acl.userId IN (:x_gids)))',
            $join->getCondition()
        );
        $this->assertSame(['x_uty', 'x_ot', 'x_uid', 'x_perm', 'x_gty', 'x_gids'], array_keys($this->getParameters($queryBuilder)));
    }

    public function testJoinAclCanBeAppliedTwiceWithDifferentPrefixes(): void
    {
        $queryBuilder = $this->createQueryBuilder();

        AccessControlEntryRepository::joinAcl($queryBuilder, 'u-1', [], 'pub', 'o', PermissionInterface::VIEW, true, 'ace_view', 'v_');
        AccessControlEntryRepository::joinAcl($queryBuilder, 'u-1', [], 'pub', 'o', PermissionInterface::EDIT, false, 'ace_edit', 'e_');

        $joins = $queryBuilder->getDQLPart('join')['o'];
        $this->assertCount(2, $joins);
        $this->assertSame('ace_view', $joins[0]->getAlias());
        $this->assertSame('ace_edit', $joins[1]->getAlias());
        $this->assertSame(PermissionInterface::VIEW, $this->getParameters($queryBuilder)['v_perm']);
        $this->assertSame(PermissionInterface::EDIT, $this->getParameters($queryBuilder)['e_perm']);
    }

    private function createQueryBuilder(): QueryBuilder
    {
        return (new QueryBuilder($this->createMock(EntityManagerInterface::class)))
            ->select('o')
            ->from(ObjectMock::class, 'o');
    }

    private function getJoin(QueryBuilder $queryBuilder): Join
    {
        $joins = $queryBuilder->getDQLPart('join')['o'];
        $this->assertCount(1, $joins);

        return $joins[0];
    }

    private function getParameters(QueryBuilder $queryBuilder): array
    {
        $parameters = [];
        foreach ($queryBuilder->getParameters() as $parameter) {
            $parameters[$parameter->getName()] = $parameter->getValue();
        }

        return $parameters;
    }
}
