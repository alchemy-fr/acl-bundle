<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Tests\Mock;

use Alchemy\AclBundle\Model\AclUserInterface;

class AclUserMock implements AclUserInterface
{
    private string $id;

    public function __construct(string $id)
    {
        $this->id = $id;
    }

    public function getId(): string
    {
        return $this->id;
    }
}
