<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Tests\Mock;

use Alchemy\AclBundle\Model\AclUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * A user as the application defines it: both a Symfony security user and an ACL user.
 */
class SecurityUserMock implements UserInterface, AclUserInterface
{
    public function __construct(private readonly string $id)
    {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUserIdentifier(): string
    {
        return $this->id;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }
}
