<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Tests\Mock;

use Alchemy\AclBundle\AclObjectInterface;

class UnmappedObjectMock implements AclObjectInterface
{
    public function getId(): string
    {
        return 'unmapped';
    }

    public function getAclOwnerId(): string
    {
        return '';
    }
}
