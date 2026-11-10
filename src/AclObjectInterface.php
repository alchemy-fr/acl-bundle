<?php

declare(strict_types=1);

namespace Alchemy\AclBundle;

interface AclObjectInterface
{
    /**
     * @return string A UUID: the ACEs store the object id in a uuid column
     */
    public function getId(): string;

    public function getAclOwnerId(): string;
}
