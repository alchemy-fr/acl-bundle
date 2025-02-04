<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Repository;

use Alchemy\AclBundle\Model\AclUserInterface;

interface UserRepositoryInterface
{
    public function getUsers(array $options = []): array;

    public function getUser(string $userId, array $options = []): ?array;

    public function getAclGroupsId(AclUserInterface $user): array;
}
