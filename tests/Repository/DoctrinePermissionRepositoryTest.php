<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Tests\Repository;

use Alchemy\AclBundle\Entity\AccessControlEntry;
use Alchemy\AclBundle\Entity\AccessControlEntryRepository;
use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AclBundle\Repository\DoctrinePermissionRepository;
use Alchemy\AclBundle\Security\PermissionInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DoctrinePermissionRepositoryTest extends TestCase
{
    public function testUpdateWithoutMetadataKeepsThoseOfTheAce(): void
    {
        $ace = $this->updateExistingAce(['v' => 1], null);

        $this->assertSame(PermissionInterface::EDIT, $ace->getMask());
        $this->assertSame(['v' => 1], $ace->getMetadata());
    }

    public function testUpdateWithMetadataReplacesThem(): void
    {
        $ace = $this->updateExistingAce(['v' => 1], []);

        $this->assertSame([], $ace->getMetadata());
    }

    private function updateExistingAce(array $existingMetadata, ?array $metadata): AccessControlEntryInterface
    {
        $existing = new AccessControlEntry();
        $existing->setMask(PermissionInterface::VIEW);
        $existing->setMetadata($existingMetadata);

        return new DoctrinePermissionRepository($this->createEntityManager([$existing]))->updateOrCreateAce(
            AccessControlEntryInterface::TYPE_USER_VALUE,
            'u-1',
            'pub',
            null,
            PermissionInterface::EDIT,
            $metadata,
        );
    }

    public function testDuplicatesAreMergedIntoTheOldestAceOnUpdate(): void
    {
        $oldest = $this->createAce(PermissionInterface::VIEW);
        $duplicate = $this->createAce(PermissionInterface::EDIT);

        $em = $this->createEntityManager([$oldest, $duplicate]);
        $em->expects($this->once())->method('remove')->with($duplicate);
        $em->expects($this->once())->method('persist')->with($oldest);

        $ace = new DoctrinePermissionRepository($em)->updateOrCreateAce(
            AccessControlEntryInterface::TYPE_USER_VALUE,
            'u-1',
            'pub',
            null,
            PermissionInterface::DELETE,
            append: true,
            previousAce: $previousAce,
        );

        $this->assertSame($oldest, $ace);
        $this->assertSame(PermissionInterface::VIEW | PermissionInterface::EDIT | PermissionInterface::DELETE, $ace->getMask());
        $this->assertSame(PermissionInterface::VIEW | PermissionInterface::EDIT, $previousAce->getMask());
    }

    public function testRevokingAPermissionRevokesItOnTheDuplicates(): void
    {
        $oldest = $this->createAce(PermissionInterface::VIEW);
        $duplicate = $this->createAce(PermissionInterface::VIEW | PermissionInterface::EDIT);

        $em = $this->createEntityManager([$oldest, $duplicate]);
        $em->expects($this->once())->method('remove')->with($duplicate);

        $ace = new DoctrinePermissionRepository($em)->updateOrCreateAce(
            AccessControlEntryInterface::TYPE_USER_VALUE,
            'u-1',
            'pub',
            null,
            PermissionInterface::VIEW,
        );

        $this->assertSame(PermissionInterface::VIEW, $ace->getMask());
    }

    public function testDeleteRemovesTheDuplicates(): void
    {
        $oldest = $this->createAce(PermissionInterface::VIEW);
        $duplicate = $this->createAce(PermissionInterface::EDIT);

        $em = $this->createEntityManager([$oldest, $duplicate]);
        $removed = [];
        $em
            ->expects($this->exactly(2))
            ->method('remove')
            ->willReturnCallback(function (object $ace) use (&$removed): void {
                $removed[] = $ace;
            });
        $em->expects($this->once())->method('flush');

        $deleted = new DoctrinePermissionRepository($em)->deleteAce(
            AccessControlEntryInterface::TYPE_USER_VALUE,
            'u-1',
            'pub',
            null,
            previousAce: $previousAce,
        );

        $this->assertTrue($deleted);
        $this->assertSame([$duplicate, $oldest], $removed);
        $this->assertSame(PermissionInterface::VIEW | PermissionInterface::EDIT, $previousAce->getMask());
    }

    public function testDeleteWithoutAceReturnsFalse(): void
    {
        $em = $this->createEntityManager([]);
        $em->expects($this->never())->method('remove');
        $em->expects($this->never())->method('flush');

        $this->assertFalse(new DoctrinePermissionRepository($em)->deleteAce(
            AccessControlEntryInterface::TYPE_USER_VALUE,
            'u-1',
            'pub',
            '42',
        ));
    }

    public function testTheAllUsersWildcardIsLookedUpAsNull(): void
    {
        $entityRepository = $this->createMock(AccessControlEntryRepository::class);
        $entityRepository
            ->expects($this->once())
            ->method('findBy')
            ->with([
                'objectType' => 'pub',
                'objectId' => '42',
                'userType' => AccessControlEntryInterface::TYPE_USER_VALUE,
                'userId' => null,
                'parentId' => null,
            ], ['createdAt' => 'ASC'])
            ->willReturn([]);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(AccessControlEntry::class)->willReturn($entityRepository);

        $ace = new DoctrinePermissionRepository($em)->updateOrCreateAce(
            AccessControlEntryInterface::TYPE_USER_VALUE,
            AccessControlEntryInterface::USER_WILDCARD,
            'pub',
            '42',
            PermissionInterface::VIEW,
        );

        $this->assertNull($ace->getUserId());
    }

    /**
     * @param AccessControlEntry[] $aces
     */
    private function createEntityManager(array $aces): EntityManagerInterface&MockObject
    {
        $entityRepository = $this->createMock(AccessControlEntryRepository::class);
        $entityRepository->method('findBy')->willReturn($aces);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(AccessControlEntry::class)->willReturn($entityRepository);

        return $em;
    }

    private function createAce(int $mask): AccessControlEntry
    {
        $ace = new AccessControlEntry();
        $ace->setMask($mask);

        return $ace;
    }
}
