<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Admin;

use Alchemy\AclBundle\Mapping\ObjectMapping;
use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AclBundle\Repository\AclUserRepositoryInterface;
use Alchemy\AclBundle\Repository\GroupRepositoryInterface;
use Alchemy\AclBundle\Repository\PermissionRepositoryInterface;
use Alchemy\AclBundle\Security\PermissionInterface;
use Doctrine\ORM\EntityManagerInterface;

class PermissionView
{
    private ObjectMapping $objectMapping;
    private PermissionRepositoryInterface $repository;
    private AclUserRepositoryInterface $userRepository;
    private GroupRepositoryInterface $groupRepository;
    private EntityManagerInterface $em;
    private ?array $enabledPermissions;

    public function __construct(
        ObjectMapping $objectMapping,
        PermissionRepositoryInterface $repository,
        AclUserRepositoryInterface $userRepository,
        GroupRepositoryInterface $groupRepository,
        EntityManagerInterface $em,
        ?array $enabledPermissions
    ) {
        $this->objectMapping = $objectMapping;
        $this->repository = $repository;
        $this->userRepository = $userRepository;
        $this->groupRepository = $groupRepository;
        $this->em = $em;
        $this->enabledPermissions = $enabledPermissions;
    }

    public function getObjectKey(string $entityClass): string
    {
        return $this->objectMapping->getObjectKey($entityClass);
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
        foreach ($this->userRepository->getAclUsers() as $user) {
            $users[$user['id']] = $user['username'];
        }
        $groups = [];
        foreach ($this->groupRepository->getGroups() as $group) {
            $groups[$group['id']] = $group['name'];
        }

        $aces = array_map(function (AccessControlEntryInterface $ace) use ($users, $groups, $permissions): array {
            $name = $ace->getUserId();
            switch ($ace->getUserType()) {
                case AccessControlEntryInterface::TYPE_USER_VALUE:
                    $name = $ace->getUserId() ? ($users[$ace->getUserId()] ?? $name) : AccessControlEntryInterface::USER_WILDCARD;
                    break;
                case AccessControlEntryInterface::TYPE_GROUP_VALUE:
                    $name = $groups[$ace->getUserId()] ?? $name;
                    break;
            }

            return [
                'userType' => $ace->getUserTypeString(),
                'userId' => $ace->getUserId() ?? AccessControlEntryInterface::USER_WILDCARD,
                'name' => $name,
                'objectId' => $ace->getObjectId(),
                'permissions' => array_map(fn (int $p): bool => $ace->hasPermission($p), $permissions),
            ];
        }, $aces);

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
}
