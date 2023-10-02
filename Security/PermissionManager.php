<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Security;

use Alchemy\AclBundle\AclObjectInterface;
use Alchemy\AclBundle\Event\AclDeleteEvent;
use Alchemy\AclBundle\Event\AclUpsertEvent;
use Alchemy\AclBundle\Mapping\ObjectMapping;
use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AclBundle\Model\AclUserInterface;
use Alchemy\AclBundle\Repository\AclUserRepositoryInterface;
use Alchemy\AclBundle\Repository\PermissionRepositoryInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class PermissionManager
{
    private ObjectMapping $objectMapper;
    private PermissionRepositoryInterface $repository;
    private EventDispatcherInterface $eventDispatcher;
    private AclUserRepositoryInterface $userRepository;
    private array $cache = [];

    public function __construct(
        ObjectMapping $objectMapper,
        PermissionRepositoryInterface $repository,
        EventDispatcherInterface $eventDispatcher,
        AclUserRepositoryInterface $userRepository
    ) {
        $this->objectMapper = $objectMapper;
        $this->repository = $repository;
        $this->eventDispatcher = $eventDispatcher;
        $this->userRepository = $userRepository;
    }

    public function isGranted(AclUserInterface $user, AclObjectInterface $object, int $permission): bool
    {
        if ($object->getAclOwnerId() === $user->getId()) {
            return true;
        }

        $aces = $this->getAces($user, $object);

        foreach ($aces as $ace) {
            if (null !== $ace && ($ace->getMask() & $permission) === $permission) {
                return true;
            }
        }

        return false;
    }

    private function getAces(AclUserInterface $user, AclObjectInterface $object): array
    {
        $objectKey = $this->objectMapper->getObjectKey($object);
        $key = $this->getCacheKey(AccessControlEntryInterface::TYPE_USER_VALUE, $user->getId(), $objectKey, $object->getId());
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $groupsId = $this->userRepository->getAclGroupsId($user);

        /** @var AccessControlEntryInterface[] $aces */
        $aces = $this->repository->getAces(
            $user->getId(),
            $groupsId,
            $objectKey,
            $object->getId()
        );

        $this->cache[$key] = $aces;

        return $aces;
    }

    public function getAllowedUsers(AclObjectInterface $object, int $permission): array
    {
        $objectKey = $this->objectMapper->getObjectKey($object);

        return $this->repository->getAllowedUserIds(
            $objectKey,
            $object->getId(),
            $permission
        );
    }

    public function getAllowedGroups(AclObjectInterface $object, int $permission): array
    {
        $objectKey = $this->objectMapper->getObjectKey($object);

        return $this->repository->getAllowedGroupIds(
            $objectKey,
            $object->getId(),
            $permission
        );
    }

    public function grantUserOnObject(string $userId, AclObjectInterface $object, int $permissions, string $parentId = null): void
    {
        $objectKey = $this->objectMapper->getObjectKey($object);

        $this->updateOrCreateAce(
            AccessControlEntryInterface::TYPE_USER_VALUE,
            $userId,
            $objectKey,
            $object->getId(),
            $permissions,
            $parentId
        );
    }

    public function grantGroupOnObject(string $userId, AclObjectInterface $object, int $permissions, string $parentId = null): void
    {
        $objectKey = $this->objectMapper->getObjectKey($object);

        $this->updateOrCreateAce(
            AccessControlEntryInterface::TYPE_GROUP_VALUE,
            $userId,
            $objectKey,
            $object->getId(),
            $permissions,
            $parentId
        );
    }

    public function findAce(
        int $userType,
        ?string $userId,
        string $objectType,
        string $objectId,
        string $parentId = null,
    ): ?AccessControlEntryInterface {
        return $this->repository->findAce($userType, $userId, $objectType, $objectId, $parentId);
    }

    /**
     * @return AccessControlEntryInterface[]
     */
    public function findAces(
        int $userType,
        ?string $userId,
        string $objectType,
        string $objectId,
    ): array {
        return $this->repository->findAces($userType, $userId, $objectType, $objectId);
    }

    public function updateOrCreateAce(
        int $userType,
        string $userId,
        string $objectType,
        ?string $objectId,
        int $permissions,
        string $parentId = null,
        bool $append = false,
    ): ?AccessControlEntryInterface {
        $ace = $this->repository->updateOrCreateAce(
            $userType,
            $userId,
            $objectType,
            $objectId,
            $permissions,
            $parentId,
            $append
        );

        unset($this->cache[$this->getCacheKey($userType, $userId, $objectType, $objectId)]);

        $this->eventDispatcher->dispatch(new AclUpsertEvent($userType, $userId, $objectType, $objectId, $permissions), AclUpsertEvent::NAME);

        return $ace;
    }

    private function getCacheKey(int $userType, string $userId, string $objectType, ?string $objectId): string
    {
        return sprintf('%d:%s:%s:%s', $userType, $userId, $objectType, $objectId);
    }

    public function deleteAce(int $userType, string $userId, string $objectType, ?string $objectId, string $parentId = null): void
    {
        if ($this->repository->deleteAce(
            $userType,
            $userId,
            $objectType,
            $objectId,
            $parentId
        )) {
            $this->eventDispatcher->dispatch(new AclDeleteEvent($userType, $userId, $objectType, $objectId), AclDeleteEvent::NAME);
        }

        unset($this->cache[$this->getCacheKey($userType, $userId, $objectType, $objectId)]);
    }
}
