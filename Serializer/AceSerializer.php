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
    )
    {
    }

    public function serialize(AccessControlEntryInterface $ace): array
    {
        $payload = [
            'id' => $ace->getId(),
            'userType' => array_search($ace->getUserType(), AccessControlEntryInterface::USER_TYPES, true),
            'userId' => $ace->getUserId(),
            'objectType' => $ace->getObjectType(),
            'objectId' => $ace->getObjectId(),
            'mask' => $ace->getMask(),
            'parentId' => $ace->getParentId(),
        ];

        $id = $ace->getUserId();
        if ($ace->getUserType() === AccessControlEntryInterface::TYPE_USER_VALUE) {
            $payload['user'] = $this->userRepository->getUser($id);
        } elseif ($ace->getUserType() === AccessControlEntryInterface::TYPE_GROUP_VALUE) {
            $payload['group'] = $this->groupRepository->getGroup($id);
        }

        return $payload;
    }
}
