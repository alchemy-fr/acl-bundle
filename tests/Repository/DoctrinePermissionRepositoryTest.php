<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Tests\Repository;

use Alchemy\AclBundle\Entity\AccessControlEntry;
use Alchemy\AclBundle\Entity\AccessControlEntryRepository;
use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AclBundle\Repository\DoctrinePermissionRepository;
use Alchemy\AclBundle\Security\PermissionInterface;
use Doctrine\ORM\EntityManagerInterface;
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

        $entityRepository = $this->createMock(AccessControlEntryRepository::class);
        $entityRepository->method('findOneBy')->willReturn($existing);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(AccessControlEntry::class)->willReturn($entityRepository);

        return new DoctrinePermissionRepository($em)->updateOrCreateAce(
            AccessControlEntryInterface::TYPE_USER_VALUE,
            'u-1',
            'pub',
            null,
            PermissionInterface::EDIT,
            $metadata,
        );
    }
}
