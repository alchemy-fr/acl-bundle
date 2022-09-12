<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Repository;

interface UserRepositoryInterface
{
    public function getUsers(int $limit, int $offset = 0): array;
}
