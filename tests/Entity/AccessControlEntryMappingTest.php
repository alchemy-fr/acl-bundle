<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Tests\Entity;

use Alchemy\AclBundle\Entity\AccessControlEntry;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Doctrine\UuidType;

/**
 * Runs the entity mapping against an in-memory SQLite database.
 */
#[RequiresPhpExtension('pdo_sqlite')]
class AccessControlEntryMappingTest extends TestCase
{
    private const string OBJECT_ID = '0b9e6c1a-1111-4a2b-9c3d-123456789abc';

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        if (!Type::hasType(UuidType::NAME)) {
            Type::addType(UuidType::NAME, UuidType::class);
        }

        $config = ORMSetup::createAttributeMetadataConfig([__DIR__.'/../../src/Entity'], true);
        $config->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER, true));
        if (method_exists($config, 'enableNativeLazyObjects')) {
            $config->enableNativeLazyObjects(true);
        }

        $this->em = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config);
        new SchemaTool($this->em)->createSchema([$this->em->getClassMetadata(AccessControlEntry::class)]);
    }

    public function testALoadedAceIsNotScheduledForUpdate(): void
    {
        $ace = new AccessControlEntry();
        $ace->setUserId('u-1');
        $ace->setObjectType('pub');
        $ace->setObjectId(self::OBJECT_ID);
        $this->em->persist($ace);
        $this->em->flush();
        $this->em->clear();

        $loaded = $this->em->find(AccessControlEntry::class, $ace->getId());
        $this->assertSame(self::OBJECT_ID, $loaded->getObjectId());

        $unitOfWork = $this->em->getUnitOfWork();
        $unitOfWork->computeChangeSets();
        $this->assertSame([], $unitOfWork->getEntityChangeSet($loaded));
    }

    public function testSettingTheSameObjectIdIsNotAChange(): void
    {
        $loaded = $this->persistAndReload(self::OBJECT_ID);
        $loaded->setObjectId(self::OBJECT_ID);

        $unitOfWork = $this->em->getUnitOfWork();
        $unitOfWork->computeChangeSets();
        $this->assertSame([], $unitOfWork->getEntityChangeSet($loaded));
    }

    public function testChangingTheObjectIdIsPersisted(): void
    {
        $otherId = '7c1f0d2e-2222-4b3c-8d4e-abcdefabcdef';

        $loaded = $this->persistAndReload(self::OBJECT_ID);
        $loaded->setObjectId($otherId);
        $this->em->flush();
        $this->em->clear();

        $this->assertSame($otherId, $this->em->find(AccessControlEntry::class, $loaded->getId())->getObjectId());
    }

    public function testATypeWideAceHasANullObjectId(): void
    {
        $ace = new AccessControlEntry();
        $ace->setUserId('u-1');
        $ace->setObjectType('pub');
        $this->em->persist($ace);
        $this->em->flush();
        $this->em->clear();

        $this->assertNull($this->em->find(AccessControlEntry::class, $ace->getId())->getObjectId());
    }

    private function persistAndReload(string $objectId): AccessControlEntry
    {
        $ace = new AccessControlEntry();
        $ace->setUserId('u-1');
        $ace->setObjectType('pub');
        $ace->setObjectId($objectId);
        $this->em->persist($ace);
        $this->em->flush();
        $this->em->clear();

        return $this->em->find(AccessControlEntry::class, $ace->getId());
    }
}
