<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Tests\Mock;

use Alchemy\AclBundle\AclObjectInterface;

/**
 * Entity with a generated id: Doctrine sets the id back to null once the entity is deleted.
 */
class GeneratedIdObjectMock implements AclObjectInterface
{
    public function __construct(public ?string $id)
    {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getAclOwnerId(): string
    {
        return '';
    }
}
