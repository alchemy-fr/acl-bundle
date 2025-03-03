<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Security\Voter;

use Alchemy\AclBundle\AclObjectInterface;
use Alchemy\AclBundle\Model\AclUserInterface;
use Alchemy\AclBundle\Security\PermissionManager;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

#[AutoconfigureTag(name: 'security.voter')]
class AclVoter extends Voter
{
    public function __construct(private readonly PermissionManager $permissionManager)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return is_numeric($attribute) && $subject instanceof AclObjectInterface;
    }

    public function supportsAttribute(string $attribute): bool
    {
        return is_numeric($attribute);
    }

    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, AclObjectInterface::class, true);
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
