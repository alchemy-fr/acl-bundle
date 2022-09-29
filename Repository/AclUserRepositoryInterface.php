<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Repository;

use Alchemy\AclBundle\Model\AclUserInterface;

interface AclUserRepositoryInterface
{
    public function getAclUsers(?int $limit = null, int $offset = 0): array;

    public function getAclGroupsId(AclUserInterface $user): array;
}
