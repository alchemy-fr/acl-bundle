<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Controller;

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
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

class PermissionController extends AbstractController
{
    private PermissionManager $permissionManager;
    private EntityManagerInterface $em;
    private ObjectMapping $objectMapping;

    public function __construct(PermissionManager $permissionManager, EntityManagerInterface $em, ObjectMapping $objectMapping)
    {
        $this->permissionManager = $permissionManager;
        $this->em = $em;
        $this->objectMapping = $objectMapping;
    }

    private function validateAuthorization(string $attribute, Request $request): void
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return;
        }

        $objectType = $request->get('objectType');
        $objectId = $request->get('objectId');

        if ($objectType && $objectId) {
            $object = $this->em->find($this->objectMapping->getClassName($objectType), $objectId);

            $this->denyAccessUnlessGranted($attribute, $object);
        }
    }

    /**
     * @Route("/ace", methods={"PUT"}, name="ace")
     */
    public function setAce(Request $request): Response
    {
        $this->validateAuthorization(SetPermissionVoter::ACL_WRITE, $request);

        $data = $this->getRequestData($request);
        $objectType = $data['objectType'] ?? null;
        $objectId = $data['objectId'] ?? null;
        $userType = $data['userType'] ?? null;
        $userId = $data['userId'] ?? null;
        $mask = $data['mask'] ?? 0;

        $objectId = !empty($objectId) ? $objectId : null;

        $userType = AccessControlEntry::getUserTypeFromString($userType);

        $this->permissionManager->updateOrCreateAce($userType, $userId, $objectType, $objectId, $mask);

        return new JsonResponse(true);
    }

    /**
     * @Route("/aces", methods={"GET"}, name="aces_index")
     */
    public function indexAces(
        Request $request,
        PermissionRepositoryInterface $repository,
        AceSerializer $aceSerializer
    ): Response {
        $this->validateAuthorization(SetPermissionVoter::ACL_READ, $request);

        $params = [
            'objectType' => $request->query->get('objectType', false),
            'objectId' => $request->query->get('objectId', false),
            'userType' => $request->query->get('userType', false),
            'userId' => $request->query->get('userId', false),
        ];

        $params = array_filter($params, function ($entry): bool {
            return false !== $entry;
        });
        $params = array_map(function ($p): ?string {
            return '' === $p || 'null' === $p ? null : $p;
        }, $params);

        if (!empty($params['userType'])) {
            $params['userType'] = AccessControlEntryInterface::USER_TYPES[$params['userType']] ?? false;
            if (false === $params['userType']) {
                throw new BadRequestHttpException('Invalid userType');
            }
        }

        $aces = $repository->findAces($params);

        return new JsonResponse(array_map(function (AccessControlEntryInterface $ace) use ($aceSerializer): array {
            return $aceSerializer->serialize($ace);
        }, $aces));
    }

    /**
     * @Route("/ace", methods={"DELETE"}, name="ace_delete")
     */
    public function deleteAce(Request $request): Response
    {
        $this->validateAuthorization(SetPermissionVoter::ACL_WRITE, $request);
        $data = $this->getRequestData($request);
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
