<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Admin;

use Alchemy\AclBundle\Mapping\ObjectMapping;
use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AclBundle\Repository\GroupRepositoryInterface;
use Alchemy\AclBundle\Repository\PermissionRepositoryInterface;
use Alchemy\AclBundle\Repository\UserRepositoryInterface;
use Alchemy\AclBundle\Security\PermissionInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Builds the parameters of the EasyAdmin permission pages.
 */
class PermissionView
{
    /**
     * @param string[]|null $enabledPermissions The permission names to show, null for all of them
     */
    public function __construct(
        private readonly ObjectMapping $objectMapping,
        private readonly PermissionRepositoryInterface $repository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly GroupRepositoryInterface $groupRepository,
        private readonly EntityManagerInterface $em,
        private readonly ?array $enabledPermissions,
    ) {
    }

    /**
     * @param string|null $id The object ID, null to manage the ACEs of every object of the type
     */
    public function getViewParameters(string $objectKey, ?string $id): array
    {
        $permissions = [];
        foreach ($this->enabledPermissions ?: array_keys(PermissionInterface::PERMISSIONS) as $name) {
            $permissions[$name] = PermissionInterface::PERMISSIONS[$name];
        }

        $users = [AccessControlEntryInterface::USER_WILDCARD => 'All users'];
        foreach ($this->userRepository->getUsers() as $user) {
            $users[$user['id']] = $user['username'];
        }
        $groups = [];
        foreach ($this->groupRepository->getGroups() as $group) {
            $groups[$group['id']] = $group['name'];
        }

        // On an object, the type-wide ACEs also apply: they are listed first, read-only
        $aces = null !== $id ? $this->repository->getObjectAces($objectKey, null) : [];
        $aces = array_merge($aces, $this->repository->getObjectAces($objectKey, $id));

        return [
            'permissions' => $permissions,
            'aces' => array_map(fn (AccessControlEntryInterface $ace): array => [
                'userType' => $ace->getUserTypeString(),
                'userId' => $ace->getUserId() ?? AccessControlEntryInterface::USER_WILDCARD,
                'name' => $this->resolveName($ace, $users, $groups),
                'wildcard' => null === $ace->getUserId(),
                'inherited' => null !== $id && null === $ace->getObjectId(),
                'mask' => $ace->getMask(),
                'permissions' => array_map($ace->hasPermission(...), $permissions),
            ], $aces),
            'users' => $users,
            'groups' => $groups,
            'object_type' => $objectKey,
            'object_id' => $id,
            'object_title' => null !== $id ? $this->getObjectTitle($objectKey, $id) : null,
        ];
    }

    private function getObjectTitle(string $objectKey, string $id): ?string
    {
        $object = $this->em->getRepository($this->objectMapping->getClassName($objectKey))->find($id);

        return $object instanceof \Stringable ? (string) $object : null;
    }

    /**
     * @param array<string, string> $users
     * @param array<string, string> $groups
     */
    private function resolveName(AccessControlEntryInterface $ace, array $users, array $groups): string
    {
        $userId = $ace->getUserId();
        if (null === $userId) {
            return $users[AccessControlEntryInterface::USER_WILDCARD];
        }

        // The lists may be partial (e.g. paginated by the repositories)
        if (AccessControlEntryInterface::TYPE_GROUP_VALUE === $ace->getUserType()) {
            return $groups[$userId]
                ?? $this->groupRepository->getGroup($userId)['name']
                ?? sprintf('Group "%s" not found', $userId);
        }

        return $users[$userId]
            ?? $this->userRepository->getUser($userId)['username']
            ?? sprintf('User "%s" not found', $userId);
    }
}
