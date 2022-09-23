<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Security\Voter;

use Alchemy\AclBundle\AclObjectInterface;
use Alchemy\AclBundle\Model\AclUserInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Security;

class SetPermissionVoter extends Voter
{
    public const ACL_READ = 'ACL_READ';
    public const ACL_WRITE = 'ACL_WRITE';
    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    protected function supports($attribute, $subject)
    {
        return in_array(
                $attribute, [
                    self::ACL_READ,
                    self::ACL_WRITE,
                ]
            ) && $subject instanceof AclObjectInterface;
    }

    /**
     * @param string $attribute
     * @param AclObjectInterface $subject
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        $user = $token->getUser();
        if (!$user instanceof AclUserInterface) {
            return false;
        }

        switch ($attribute) {
            case self::ACL_READ:
            case self::ACL_WRITE:
                return $subject->getAclOwnerId() === $user->getId();
            default:
                return false;
        }
    }
}
