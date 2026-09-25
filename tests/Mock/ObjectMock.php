<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Tests\Mock;

use Alchemy\AclBundle\AclObjectInterface;

class ObjectMock implements AclObjectInterface
{
    public function __construct(
        private readonly string $id,
        private readonly string $aclOwnerId = '',
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getAclOwnerId(): string
    {
        return $this->aclOwnerId;
    }
}
