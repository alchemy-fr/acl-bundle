<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Repository;

interface GroupRepositoryInterface
{
    public function getGroups(?int $limit = null, int $offset = 0): array;

    public function getGroup(string $groupId): ?array;
}
