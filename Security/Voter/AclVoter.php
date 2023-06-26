<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Security\Voter;

use Alchemy\AclBundle\AclObjectInterface;
use Alchemy\AclBundle\Model\AclUserInterface;
use Alchemy\AclBundle\Security\PermissionManager;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class AclVoter extends Voter
{
    public function __construct(private readonly PermissionManager $permissionManager)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return is_numeric($attribute) && $subject instanceof AclObjectInterface;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if ($user instanceof AclUserInterface) {
            return $this->permissionManager->isGranted($user, $subject, (int) $attribute);
        }

        return false;
    }
}
