<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Tests\Entity;

use Alchemy\AclBundle\Entity\AccessControlEntry;
use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AclBundle\Security\PermissionInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class AccessControlEntryTest extends TestCase
{
    public function testDefaults(): void
    {
        $ace = new AccessControlEntry();

        $this->assertTrue(Uuid::isValid($ace->getId()));
        $this->assertSame(AccessControlEntryInterface::TYPE_USER_VALUE, $ace->getUserType());
        $this->assertSame(AccessControlEntryInterface::TYPE_USER, $ace->getUserTypeString());
        $this->assertNull($ace->getUserId());
        $this->assertNull($ace->getObjectType());
        $this->assertNull($ace->getObjectId());
        $this->assertNull($ace->getParentId());
        $this->assertSame(0, $ace->getMask());
        $this->assertSame([], $ace->getPermissions());
        $this->assertSame([], $ace->getMetadata());
        $this->assertInstanceOf(\DateTimeImmutable::class, $ace->getCreatedAt());
    }

    public function testEachAceGetsItsOwnId(): void
    {
        $this->assertNotSame((new AccessControlEntry())->getId(), (new AccessControlEntry())->getId());
    }

    public function testSetters(): void
    {
        $ace = new AccessControlEntry();
        $ace->setUserType(AccessControlEntryInterface::TYPE_GROUP_VALUE);
        $ace->setUserId('g-1');
        $ace->setObjectType('publication');
        $ace->setObjectId('pub-42');
        $ace->setParentId('p:pub-41');
        $ace->setMetadata(['reason' => 'shared']);

        $this->assertSame(AccessControlEntryInterface::TYPE_GROUP_VALUE, $ace->getUserType());
        $this->assertSame('g-1', $ace->getUserId());
        $this->assertSame('publication', $ace->getObjectType());
        $this->assertSame('pub-42', $ace->getObjectId());
        $this->assertSame('p:pub-41', $ace->getParentId());
        $this->assertSame(['reason' => 'shared'], $ace->getMetadata());
    }

    public function testNullableFieldsCanBeReset(): void
    {
        $ace = new AccessControlEntry();
        $ace->setUserId('u-1');
        $ace->setObjectId('pub-42');
        $ace->setParentId('p:1');

        $ace->setUserId(null);
        $ace->setObjectId(null);
        $ace->setParentId(null);

        $this->assertNull($ace->getUserId());
        $this->assertNull($ace->getObjectId());
        $this->assertNull($ace->getParentId());
    }

    public function testAddPermissionSetsBits(): void
    {
        $ace = new AccessControlEntry();
        $ace->addPermission(PermissionInterface::VIEW);
        $ace->addPermission(PermissionInterface::EDIT);

        $this->assertSame(PermissionInterface::VIEW | PermissionInterface::EDIT, $ace->getMask());
        $this->assertTrue($ace->hasPermission(PermissionInterface::VIEW));
        $this->assertTrue($ace->hasPermission(PermissionInterface::EDIT));
        $this->assertFalse($ace->hasPermission(PermissionInterface::DELETE));
    }

    public function testAddPermissionIsIdempotent(): void
    {
        $ace = new AccessControlEntry();
        $ace->addPermission(PermissionInterface::VIEW);
        $ace->addPermission(PermissionInterface::VIEW);

        $this->assertSame(PermissionInterface::VIEW, $ace->getMask());
    }

    public function testHasPermissionRequiresEveryBitOfTheMask(): void
    {
        $ace = new AccessControlEntry();
        $ace->addPermission(PermissionInterface::EDIT);

        $this->assertTrue($ace->hasPermission(PermissionInterface::EDIT));
        $this->assertFalse($ace->hasPermission(PermissionInterface::EDIT | PermissionInterface::DELETE));
    }

    public function testRemovePermission(): void
    {
        $ace = new AccessControlEntry();
        $ace->setMask(PermissionInterface::VIEW | PermissionInterface::EDIT | PermissionInterface::DELETE);

        $ace->removePermission(PermissionInterface::EDIT);

        $this->assertSame(PermissionInterface::VIEW | PermissionInterface::DELETE, $ace->getMask());
        $this->assertFalse($ace->hasPermission(PermissionInterface::EDIT));
    }

    public function testRemoveMissingPermissionIsANoOp(): void
    {
        $ace = new AccessControlEntry();
        $ace->setMask(PermissionInterface::VIEW);

        $ace->removePermission(PermissionInterface::DELETE);

        $this->assertSame(PermissionInterface::VIEW, $ace->getMask());
    }

    public function testResetPermissions(): void
    {
        $ace = new AccessControlEntry();
        $ace->setMask(PermissionInterface::OWNER | PermissionInterface::VIEW);

        $ace->resetPermissions();

        $this->assertSame(0, $ace->getMask());
        $this->assertSame([], $ace->getPermissions());
    }

    public function testSetPermissionsAppendsToTheMask(): void
    {
        $ace = new AccessControlEntry();
        $ace->setMask(PermissionInterface::VIEW);

        $ace->setPermissions([PermissionInterface::EDIT, PermissionInterface::SHARE]);

        $this->assertSame(
            PermissionInterface::VIEW | PermissionInterface::EDIT | PermissionInterface::SHARE,
            $ace->getMask()
        );
    }

    #[DataProvider('maskProvider')]
    public function testGetPermissionsDecomposesTheMask(int $mask, array $expected): void
    {
        $ace = new AccessControlEntry();
        $ace->setMask($mask);

        $this->assertSame($expected, $ace->getPermissions());
    }

    public static function maskProvider(): iterable
    {
        yield 'empty' => [0, []];
        yield 'single bit' => [PermissionInterface::DELETE, [PermissionInterface::DELETE]];
        yield 'view + create + edit' => [7, [PermissionInterface::VIEW, PermissionInterface::CREATE, PermissionInterface::EDIT]];
        yield 'child permission' => [
            PermissionInterface::VIEW | PermissionInterface::CHILD_SHARE,
            [PermissionInterface::VIEW, PermissionInterface::CHILD_SHARE],
        ];
        yield 'all known permissions' => [
            array_sum(PermissionInterface::PERMISSIONS),
            array_values(PermissionInterface::PERMISSIONS),
        ];
    }

    public function testPermissionConstantsAreDistinctPowersOfTwo(): void
    {
        $values = array_values(PermissionInterface::PERMISSIONS);

        $this->assertSame($values, array_unique($values));
        foreach ($values as $value) {
            $this->assertSame(1, substr_count(decbin($value), '1'), sprintf('%d is not a power of two', $value));
        }
    }

    #[DataProvider('userTypeProvider')]
    public function testUserTypeConversion(string $string, int $code): void
    {
        $this->assertSame($code, AccessControlEntry::getUserTypeFromString($string));
        $this->assertSame($string, AccessControlEntry::getUserTypeFromCode($code));

        $ace = new AccessControlEntry();
        $ace->setUserTypeString($string);

        $this->assertSame($code, $ace->getUserType());
        $this->assertSame($string, $ace->getUserTypeString());
    }

    public static function userTypeProvider(): iterable
    {
        yield 'user' => [AccessControlEntryInterface::TYPE_USER, AccessControlEntryInterface::TYPE_USER_VALUE];
        yield 'group' => [AccessControlEntryInterface::TYPE_GROUP, AccessControlEntryInterface::TYPE_GROUP_VALUE];
    }
}
