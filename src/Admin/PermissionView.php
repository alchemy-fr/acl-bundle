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

class PermissionView
{
    public function __construct(private readonly ObjectMapping $objectMapping, private readonly PermissionRepositoryInterface $repository, private readonly UserRepositoryInterface $userRepository, private readonly GroupRepositoryInterface $groupRepository, private readonly EntityManagerInterface $em, private readonly ?array $enabledPermissions)
    {
    }

    public function getViewParameters(string $objectKey, ?string $id): array
    {
        $permissions = [];
        foreach ($this->enabledPermissions as $key) {
            $permissions[$key] = PermissionInterface::PERMISSIONS[$key];
        }

        $aces = [];
        if (null !== $id) {
            $aces = array_merge($aces, $this->repository->getObjectAces($objectKey, null));
        }
        $aces = array_merge($aces, $this->repository->getObjectAces($objectKey, $id));

        $users = [
            AccessControlEntryInterface::USER_WILDCARD => 'All users',
        ];
        foreach ($this->userRepository->getUsers() as $user) {
            $users[$user['id']] = $user['username'];
        }
        $groups = [];
        foreach ($this->groupRepository->getGroups() as $group) {
            $groups[$group['id']] = $group['name'];
        }

        $aces = array_map(fn (AccessControlEntryInterface $ace): array => [
            'userType' => $ace->getUserTypeString(),
            'userId' => $ace->getUserId() ?? AccessControlEntryInterface::USER_WILDCARD,
            'name' => $this->resolveUserName($ace),
            'objectId' => $ace->getObjectId(),
            'permissions' => array_map(fn (int $p): bool => $ace->hasPermission($p), $permissions),
        ], $aces);

        $objectTitle = null;
        if ($id) {
            $object = $this->em->getRepository($this->objectMapping->getClassName($objectKey))->find($id);
            if (null !== $object && method_exists($object, '__toString')) {
                $objectTitle = (string) $object;
            }
        }

        $params = [
            'USER_WILDCARD' => AccessControlEntryInterface::USER_WILDCARD,
            'permissions' => $permissions,
            'aces' => $aces,
            'users' => $users,
            'groups' => $groups,
            'object_type' => $objectKey,
            'object_title' => $objectTitle,
        ];

        if (null !== $id) {
            $params['object_id'] = $id;
        }

        return $params;
    }

    private function resolveUserName(AccessControlEntryInterface $ace): string
    {
        $userId = $ace->getUserId();

        if (null !== $userId) {
            switch ($ace->getUserType()) {
                case AccessControlEntryInterface::TYPE_USER_VALUE:
                    if (null !== $user = $this->userRepository->getUser($userId)) {
                        return $user['username'];
                    }

                    return sprintf('User "%s" not found', $userId);
                case AccessControlEntryInterface::TYPE_GROUP_VALUE:
                    if (null !== $group = $this->groupRepository->getGroup($userId)) {
                        return $group['name'];
                    }

                    return sprintf('Group "%s" not found', $userId);
            }
        }

        return AccessControlEntryInterface::USER_WILDCARD;
    }
}
