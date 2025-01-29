<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Event;

class AclDeleteEvent extends AclEvent
{
    public const NAME = 'acl.delete';
}
