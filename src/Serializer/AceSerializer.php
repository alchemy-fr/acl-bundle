<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Serializer;

use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AclBundle\Repository\GroupRepositoryInterface;
use Alchemy\AclBundle\Repository\UserRepositoryInterface;

readonly class AceSerializer
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private GroupRepositoryInterface $groupRepository,
    ) {
    }

    public function serialize(AccessControlEntryInterface $ace): array
    {
        $userId = $ace->getUserId();

        $payload = [
            'id' => $ace->getId(),
            'userType' => array_search($ace->getUserType(), AccessControlEntryInterface::USER_TYPES, true),
            'userId' => $ace->getUserId(),
            'objectType' => $ace->getObjectType(),
            'objectId' => $ace->getObjectId(),
            'mask' => $ace->getMask(),
            'parentId' => $ace->getParentId(),
        ];

        if (null !== $userId) {
            if (AccessControlEntryInterface::TYPE_USER_VALUE === $ace->getUserType()) {
                $payload['user'] = $this->userRepository->getUser($userId);
            } elseif (AccessControlEntryInterface::TYPE_GROUP_VALUE === $ace->getUserType()) {
                $payload['group'] = $this->groupRepository->getGroup($userId);
            }
        }

        return $payload;
    }
}
