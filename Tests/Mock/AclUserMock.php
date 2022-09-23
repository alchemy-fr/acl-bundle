<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Tests\Mock;

use Alchemy\AclBundle\Model\AclUserInterface;

class AclUserMock implements AclUserInterface
{
    private string $id;
    private array $groupIds;

    public function __construct(string $id, array $groupIds)
    {
        $this->id = $id;
        $this->groupIds = $groupIds;
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
