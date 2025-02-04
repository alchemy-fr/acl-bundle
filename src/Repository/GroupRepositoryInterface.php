<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Repository;

interface GroupRepositoryInterface
{
    public function getGroups(array $options = []): array;

    public function getGroup(string $groupId, array $options = []): ?array;
}
