<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Security;

interface PermissionInterface
{
    public const int VIEW = 1;
    public const int CREATE = 2;
    public const int EDIT = 4;
    public const int DELETE = 8;
    public const int UNDELETE = 16;
    public const int OPERATOR = 32;
    public const int MASTER = 64;
    public const int OWNER = 128;
    public const int SHARE = 256;
    public const int CHILD_CREATE = 512;
    public const int CHILD_EDIT = 1024;
    public const int CHILD_DELETE = 2048;
    public const int CHILD_UNDELETE = 4096;
    public const int CHILD_OPERATOR = 8192;
    public const int CHILD_MASTER = 16384;
    public const int CHILD_OWNER = 32768;
    public const int CHILD_SHARE = 65536;

    public const array PERMISSIONS = [
        'VIEW' => self::VIEW,
        'CREATE' => self::CREATE,
        'EDIT' => self::EDIT,
        'DELETE' => self::DELETE,
        'UNDELETE' => self::UNDELETE,
        'OPERATOR' => self::OPERATOR,
        'MASTER' => self::MASTER,
        'OWNER' => self::OWNER,
        'SHARE' => self::SHARE,
        'CHILD_CREATE' => self::CHILD_CREATE,
        'CHILD_EDIT' => self::CHILD_EDIT,
        'CHILD_DELETE' => self::CHILD_DELETE,
        'CHILD_UNDELETE' => self::CHILD_UNDELETE,
        'CHILD_OPERATOR' => self::CHILD_OPERATOR,
        'CHILD_MASTER' => self::CHILD_MASTER,
        'CHILD_OWNER' => self::CHILD_OWNER,
        'CHILD_SHARE' => self::CHILD_SHARE,
    ];
}
