<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Repository;

use Alchemy\AclBundle\Model\AclUserInterface;

interface UserRepositoryInterface
{
    public function getUsers(int $limit, int $offset = 0): array;

    public function getUser(string $userId): ?array;

    public function getAclGroupsId(AclUserInterface $user): array;
}
