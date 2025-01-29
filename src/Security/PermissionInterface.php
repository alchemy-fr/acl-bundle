<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Security;

interface PermissionInterface
{
    public const VIEW = 1;
    public const CREATE = 2;
    public const EDIT = 4;
    public const DELETE = 8;
    public const UNDELETE = 16;
    public const OPERATOR = 32;
    public const MASTER = 64;
    public const OWNER = 128;
    public const SHARE = 256;

    public const PERMISSIONS = [
        'VIEW' => self::VIEW,
        'CREATE' => self::CREATE,
        'EDIT' => self::EDIT,
        'DELETE' => self::DELETE,
        'UNDELETE' => self::UNDELETE,
        'OPERATOR' => self::OPERATOR,
        'MASTER' => self::MASTER,
        'OWNER' => self::OWNER,
        'SHARE' => self::SHARE,
    ];
}
