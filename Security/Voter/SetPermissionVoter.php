<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Security\Voter;

use Alchemy\AclBundle\AclObjectInterface;
use Alchemy\AclBundle\Model\AclUserInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class SetPermissionVoter extends Voter
{
    public const ACL_READ = 'ACL_READ';
    public const ACL_WRITE = 'ACL_WRITE';

    public function __construct(private readonly Security $security)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array(
            $attribute, [
                self::ACL_READ,
                self::ACL_WRITE,
            ],
                true
        ) && $subject instanceof AclObjectInterface;
    }

    public function supportsAttribute(string $attribute): bool
    {
        return in_array(
            $attribute, [
                self::ACL_READ,
                self::ACL_WRITE,
            ],
            true
        );
    }

    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, AclObjectInterface::class, true);
    }

    /**
     * @param AclObjectInterface $subject
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        $user = $token->getUser();
        if (!$user instanceof AclUserInterface) {
            return false;
        }

        return match ($attribute) {
            self::ACL_READ, self::ACL_WRITE => $subject->getAclOwnerId() === $user->getId(),
            default => false,
        };
    }
}
