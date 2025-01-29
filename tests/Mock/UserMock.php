<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Tests\Mock;

use Alchemy\AclBundle\Model\AclUserInterface;

class UserMock implements AclUserInterface
{
    public function __construct(private readonly string $id, private readonly array $groupIds)
    {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getGroupIds(): array
    {
        return $this->groupIds;
    }
}
