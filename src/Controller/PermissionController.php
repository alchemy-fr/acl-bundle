<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Controller;

use Alchemy\AclBundle\AclObjectInterface;
use Alchemy\AclBundle\Mapping\ObjectMapping;
use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AclBundle\Repository\PermissionRepositoryInterface;
use Alchemy\AclBundle\Security\ObjectTypeSubject;
use Alchemy\AclBundle\Security\PermissionManager;
use Alchemy\AclBundle\Security\Voter\SetPermissionVoter;
use Alchemy\AclBundle\Serializer\AceSerializer;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\Routing\Attribute\Route;

class PermissionController extends AbstractController
{
    private const int USER_ID_MAX_LENGTH = 36;
    private const int PARENT_ID_MAX_LENGTH = 39;
    private const int MASK_MAX = 0x7FFFFFFF;

    public function __construct(
        private readonly PermissionManager $permissionManager,
        private readonly EntityManagerInterface $em,
        private readonly ObjectMapping $objectMapping,
    ) {
    }

    #[Route(path: '/ace', name: 'ace', methods: ['PUT'])]
    public function setAce(Request $request, AceSerializer $aceSerializer): Response
    {
        $data = $this->getRequestData($request);
        $key = $this->readAceKey($data);
        $mask = $this->readMask($data);
        // Clients which do not handle the metadata (e.g. the EasyAdmin editor) must not erase them
        $metadata = array_key_exists('metadata', $data) ? $this->readMetadata($data['metadata']) : null;

        $this->validateAuthorization(SetPermissionVoter::ACL_WRITE, $key['objectType'], $key['objectId']);

        $ace = $this->permissionManager->updateOrCreateAce(
            $key['userType'],
            $key['userId'],
            $key['objectType'],
            $key['objectId'],
            $mask,
            $metadata,
            $key['parentId'],
        );

        return new JsonResponse($aceSerializer->serialize($ace));
    }

    #[Route(path: '/aces', name: 'aces_index', methods: ['GET'])]
    public function indexAces(
        Request $request,
        PermissionRepositoryInterface $repository,
        AceSerializer $aceSerializer,
    ): Response {
        $queryKeys = [
            'objectType',
            'objectId',
            'userType',
            'userId',
            'parentId',
            'userIdWildcard',
            'objectIdWildcard',
        ];

        $params = array_combine(
            $queryKeys,
            array_map(
                fn (string $key) => $request->query->get($key, false),
                $queryKeys
            )
        );

        $params = array_filter($params, fn ($entry): bool => false !== $entry);
        $params = array_map(fn ($p): ?string => '' === $p || 'null' === $p ? null : $p, $params);

        [$objectType, $objectId] = $this->readObject($params['objectType'] ?? null, $params['objectId'] ?? null);
        $this->validateAuthorization(SetPermissionVoter::ACL_READ, $objectType, $objectId);

        // A non-admin may only list the ACEs of the object (or type-wide ACEs) the authorization was checked on
        if (!$this->isGranted('ROLE_ADMIN')) {
            $params['objectType'] = $objectType;
            $params['objectId'] = $objectId;
        }

        if (!empty($params['userType'])) {
            $params['userType'] = AccessControlEntryInterface::USER_TYPES[$params['userType']] ?? false;
            if (false === $params['userType']) {
                throw new BadRequestHttpException('Invalid userType');
            }
        }

        $aces = $repository->findAcesByParams($params);

        return new JsonResponse(array_map($aceSerializer->serialize(...), $aces));
    }

    #[Route(path: '/ace', name: 'ace_delete', methods: ['DELETE'])]
    public function deleteAce(Request $request): Response
    {
        $key = $this->readAceKey($this->getRequestData($request));

        $this->validateAuthorization(SetPermissionVoter::ACL_WRITE, $key['objectType'], $key['objectId']);

        $this->permissionManager->deleteAce(
            $key['userType'],
            $key['userId'],
            $key['objectType'],
            $key['objectId'],
            $key['parentId'],
        );

        return new JsonResponse(true);
    }

    private function validateAuthorization(string $attribute, ?string $objectType, ?string $objectId): void
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return;
        }

        if (null === $objectType) {
            throw new AccessDeniedHttpException();
        }

        $className = $this->objectMapping->getClassName($objectType);

        // No object id: the ACE applies to every object of the type, the voters
        // decide on the type itself.
        if (null === $objectId) {
            if ($this->isGranted($attribute, new ObjectTypeSubject($objectType, $className))) {
                return;
            }

            throw new AccessDeniedHttpException();
        }

        $object = $this->em->find($className, $objectId);

        if (
            $object instanceof AclObjectInterface
            && $this->isGranted($attribute, $object)
        ) {
            return;
        }

        throw new AccessDeniedHttpException();
    }

    /**
     * @return array{userType: int, userId: string, objectType: string, objectId: ?string, parentId: ?string}
     */
    private function readAceKey(array $data): array
    {
        $userType = $data['userType'] ?? null;
        if (!is_string($userType) || !isset(AccessControlEntryInterface::USER_TYPES[$userType])) {
            throw new BadRequestHttpException('Invalid userType');
        }

        $userId = $data['userId'] ?? null;
        if (!is_string($userId) || '' === $userId || strlen($userId) > self::USER_ID_MAX_LENGTH) {
            throw new BadRequestHttpException('Invalid userId');
        }

        [$objectType, $objectId] = $this->readObject($data['objectType'] ?? null, $data['objectId'] ?? null);
        if (null === $objectType) {
            throw new BadRequestHttpException('Missing objectType');
        }

        $parentId = $data['parentId'] ?? null;
        if ('' === $parentId) {
            $parentId = null;
        }
        if (null !== $parentId && (!is_string($parentId) || strlen($parentId) > self::PARENT_ID_MAX_LENGTH)) {
            throw new BadRequestHttpException('Invalid parentId');
        }

        return [
            'userType' => AccessControlEntryInterface::USER_TYPES[$userType],
            'userId' => $userId,
            'objectType' => $objectType,
            'objectId' => $objectId,
            'parentId' => $parentId,
        ];
    }

    /**
     * @return array{?string, ?string} The object type and the object id, null when empty
     */
    private function readObject(mixed $objectType, mixed $objectId): array
    {
        if (null === $objectType || '' === $objectType) {
            $objectType = null;
        } elseif (!is_string($objectType) || !in_array($objectType, $this->objectMapping->getObjectTypes(), true)) {
            throw new BadRequestHttpException('Invalid objectType');
        }

        if (null === $objectId || '' === $objectId) {
            $objectId = null;
        } elseif (!is_string($objectId) || !Uuid::isValid($objectId)) {
            throw new BadRequestHttpException('Invalid objectId: a UUID is expected');
        }

        return [$objectType, $objectId];
    }

    private function readMask(array $data): int
    {
        $mask = $data['mask'] ?? 0;
        if (is_string($mask) && ctype_digit($mask)) {
            $mask = (int) $mask;
        }

        if (!is_int($mask) || $mask < 0 || $mask > self::MASK_MAX) {
            throw new BadRequestHttpException('Invalid mask');
        }

        return $mask;
    }

    private function readMetadata(mixed $metadata): array
    {
        if (null === $metadata) {
            return [];
        }

        if (!is_array($metadata)) {
            throw new BadRequestHttpException('Invalid metadata: a JSON object is expected');
        }

        return $metadata;
    }

    private function getRequestData(Request $request): array
    {
        // A cross-site form can send a form-encoded body without a CORS preflight, a JSON one cannot
        if ('json' !== $request->getContentTypeFormat()) {
            throw new UnsupportedMediaTypeHttpException('The body must be JSON');
        }

        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new BadRequestHttpException('Invalid JSON body: '.$e->getMessage(), $e);
        }

        if (!is_array($data)) {
            throw new BadRequestHttpException('The body must be a JSON object');
        }

        return $data;
    }
}
