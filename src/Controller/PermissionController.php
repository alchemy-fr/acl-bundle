<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Controller;

use Alchemy\AclBundle\AclObjectInterface;
use Alchemy\AclBundle\Entity\AccessControlEntry;
use Alchemy\AclBundle\Mapping\ObjectMapping;
use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AclBundle\Repository\PermissionRepositoryInterface;
use Alchemy\AclBundle\Security\PermissionManager;
use Alchemy\AclBundle\Security\Voter\SetPermissionVoter;
use Alchemy\AclBundle\Serializer\AceSerializer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

class PermissionController extends AbstractController
{
    public function __construct(private readonly PermissionManager $permissionManager, private readonly EntityManagerInterface $em, private readonly ObjectMapping $objectMapping)
    {
    }

    private function validateAuthorization(string $attribute, Request $request): array
    {
        $data = $this->getRequestData($request);
        if ($this->isGranted('ROLE_ADMIN')) {
            return $data;
        }

        if (!empty($data['objectType']) && !empty($data['objectId'])) {
            $object = $this->em->find($this->objectMapping->getClassName($data['objectType']), $data['objectId']);

            if (
                $object instanceof AclObjectInterface
                && $this->isGranted($attribute, $object)
            ) {
                return $data;
            }
        }

        throw new AccessDeniedHttpException();
    }

    #[Route(path: '/ace', name: 'ace', methods: ['PUT'])]
    public function setAce(Request $request, AceSerializer $aceSerializer): Response
    {
        $data = $this->validateAuthorization(SetPermissionVoter::ACL_WRITE, $request);

        $objectType = $data['objectType'] ?? null;
        $objectId = $data['objectId'] ?? null;
        $userType = $data['userType'] ?? null;
        $userId = $data['userId'] ?? null;
        $mask = (int) ($data['mask'] ?? 0);

        $objectId = !empty($objectId) ? $objectId : null;

        $userType = AccessControlEntry::getUserTypeFromString($userType);

        $ace = $this->permissionManager->updateOrCreateAce($userType, $userId, $objectType, $objectId, $mask);

        return new JsonResponse($aceSerializer->serialize($ace));
    }

    #[Route(path: '/aces', name: 'aces_index', methods: ['GET'])]
    public function indexAces(
        Request $request,
        PermissionRepositoryInterface $repository,
        AceSerializer $aceSerializer,
    ): Response {
        $this->validateAuthorization(SetPermissionVoter::ACL_READ, $request);

        $params = [
            'objectType' => $request->query->get('objectType', false),
            'objectId' => $request->query->get('objectId', false),
            'userType' => $request->query->get('userType', false),
            'userId' => $request->query->get('userId', false),
        ];

        $params = array_filter($params, fn ($entry): bool => false !== $entry);
        $params = array_map(fn ($p): ?string => '' === $p || 'null' === $p ? null : $p, $params);

        if (!empty($params['userType'])) {
            $params['userType'] = AccessControlEntryInterface::USER_TYPES[$params['userType']] ?? false;
            if (false === $params['userType']) {
                throw new BadRequestHttpException('Invalid userType');
            }
        }

        $aces = $repository->findAcesByParams($params);

        return new JsonResponse(array_map(fn (AccessControlEntryInterface $ace): array => $aceSerializer->serialize($ace), $aces));
    }

    #[Route(path: '/ace', name: 'ace_delete', methods: ['DELETE'])]
    public function deleteAce(Request $request): Response
    {
        $data = $this->validateAuthorization(SetPermissionVoter::ACL_WRITE, $request);
        $objectType = $data['objectType'] ?? null;
        $objectId = $data['objectId'] ?? null;
        $userType = $data['userType'] ?? null;
        $userId = $data['userId'] ?? null;

        $objectId = !empty($objectId) ? $objectId : null;

        $userType = AccessControlEntry::getUserTypeFromString($userType);

        $this->permissionManager->deleteAce($userType, $userId, $objectType, $objectId);

        return new JsonResponse(true);
    }

    private function getRequestData(Request $request): array
    {
        if ('json' !== $request->getContentType() || empty($request->getContent())) {
            if ('GET' === $request->getMethod()) {
                return $request->query->all();
            }

            return $request->request->all();
        }

        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new BadRequestHttpException('Invalid JSON body: '.$e->getMessage(), $e);
        }

        return is_array($data) ? $data : [];
    }
}
