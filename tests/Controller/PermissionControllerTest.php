<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Tests\Controller;

use Alchemy\AclBundle\Controller\PermissionController;
use Alchemy\AclBundle\Entity\AccessControlEntry;
use Alchemy\AclBundle\Mapping\ObjectMapping;
use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AclBundle\Repository\GroupRepositoryInterface;
use Alchemy\AclBundle\Repository\PermissionRepositoryInterface;
use Alchemy\AclBundle\Repository\UserRepositoryInterface;
use Alchemy\AclBundle\Security\ObjectTypeSubject;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AclBundle\Security\PermissionManager;
use Alchemy\AclBundle\Security\Voter\SetPermissionVoter;
use Alchemy\AclBundle\Serializer\AceSerializer;
use Alchemy\AclBundle\Tests\Mock\ObjectMock;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class PermissionControllerTest extends TestCase
{
    private PermissionManager&MockObject $permissionManager;
    private EntityManagerInterface&MockObject $em;
    private AuthorizationCheckerInterface&MockObject $authorizationChecker;
    private PermissionController $controller;
    private AceSerializer $serializer;

    protected function setUp(): void
    {
        $this->permissionManager = $this->createMock(PermissionManager::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);

        $this->controller = new PermissionController(
            $this->permissionManager,
            $this->em,
            new ObjectMapping(['pub' => ObjectMock::class]),
        );

        $container = new Container();
        $container->set('security.authorization_checker', $this->authorizationChecker);
        $this->controller->setContainer($container);

        $this->serializer = new AceSerializer(
            $this->createMock(UserRepositoryInterface::class),
            $this->createMock(GroupRepositoryInterface::class),
        );
    }

    public function testAdminCanSetAnAce(): void
    {
        $this->grantOnly('ROLE_ADMIN');
        $this->em->expects($this->never())->method('find');

        $ace = $this->createAce();
        $this->permissionManager
            ->expects($this->once())
            ->method('updateOrCreateAce')
            ->with(
                AccessControlEntryInterface::TYPE_USER_VALUE,
                'u-1',
                'pub',
                'pub-42',
                PermissionInterface::VIEW | PermissionInterface::EDIT,
                ['reason' => 'shared'],
            )
            ->willReturn($ace);

        $response = $this->controller->setAce($this->jsonRequest('PUT', [
            'userType' => 'user',
            'userId' => 'u-1',
            'objectType' => 'pub',
            'objectId' => 'pub-42',
            'mask' => PermissionInterface::VIEW | PermissionInterface::EDIT,
            'metadata' => ['reason' => 'shared'],
        ]), $this->serializer);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $payload = json_decode($response->getContent(), true);
        $this->assertSame($ace->getId(), $payload['id']);
        $this->assertSame(PermissionInterface::VIEW | PermissionInterface::EDIT, $payload['mask']);
    }

    public function testSetAceWithAnEmptyObjectIdTargetsTheWholeType(): void
    {
        $this->grantOnly('ROLE_ADMIN');

        $this->permissionManager
            ->expects($this->once())
            ->method('updateOrCreateAce')
            ->with(AccessControlEntryInterface::TYPE_GROUP_VALUE, 'g-1', 'pub', null, PermissionInterface::VIEW, [])
            ->willReturn($this->createAce());

        $this->controller->setAce($this->jsonRequest('PUT', [
            'userType' => 'group',
            'userId' => 'g-1',
            'objectType' => 'pub',
            'objectId' => '',
            'mask' => PermissionInterface::VIEW,
        ]), $this->serializer);
    }

    public function testSetAceAcceptsFormEncodedBodies(): void
    {
        $this->grantOnly('ROLE_ADMIN');

        $this->permissionManager
            ->expects($this->once())
            ->method('updateOrCreateAce')
            ->with(AccessControlEntryInterface::TYPE_USER_VALUE, 'u-1', 'pub', 'pub-42', 7, [])
            ->willReturn($this->createAce());

        $request = Request::create('/permissions/ace', 'PUT', [
            'userType' => 'user',
            'userId' => 'u-1',
            'objectType' => 'pub',
            'objectId' => 'pub-42',
            'mask' => '7',
        ]);

        $this->controller->setAce($request, $this->serializer);
    }

    public function testOwnerOfTheObjectCanSetAnAce(): void
    {
        $object = new ObjectMock('pub-42', 'u-1');
        $this->em->method('find')->with(ObjectMock::class, 'pub-42')->willReturn($object);
        $this->authorizationChecker
            ->method('isGranted')
            ->willReturnCallback(fn (mixed $attribute, mixed $subject = null): bool => SetPermissionVoter::ACL_WRITE === $attribute && $subject === $object);

        $this->permissionManager
            ->expects($this->once())
            ->method('updateOrCreateAce')
            ->willReturn($this->createAce());

        $this->controller->setAce($this->jsonRequest('PUT', $this->userAceBody()), $this->serializer);
    }

    public function testSetAceIsDeniedWhenTheVotersDeny(): void
    {
        $this->em->method('find')->willReturn(new ObjectMock('pub-42', 'someone-else'));
        $this->authorizationChecker->method('isGranted')->willReturn(false);
        $this->permissionManager->expects($this->never())->method('updateOrCreateAce');

        $this->expectException(AccessDeniedHttpException::class);

        $this->controller->setAce($this->jsonRequest('PUT', $this->userAceBody()), $this->serializer);
    }

    public function testSetAceIsDeniedWhenTheObjectDoesNotExist(): void
    {
        $this->em->method('find')->willReturn(null);
        $this->authorizationChecker->method('isGranted')->willReturnCallback(fn (mixed $attribute): bool => 'ROLE_ADMIN' !== $attribute);
        $this->permissionManager->expects($this->never())->method('updateOrCreateAce');

        $this->expectException(AccessDeniedHttpException::class);

        $this->controller->setAce($this->jsonRequest('PUT', $this->userAceBody()), $this->serializer);
    }

    public function testSetAceIsDeniedWhenTheObjectIsNotAnAclObject(): void
    {
        $this->em->method('find')->willReturn(new \stdClass());
        $this->authorizationChecker->method('isGranted')->willReturnCallback(fn (mixed $attribute): bool => 'ROLE_ADMIN' !== $attribute);

        $this->expectException(AccessDeniedHttpException::class);

        $this->controller->setAce($this->jsonRequest('PUT', $this->userAceBody()), $this->serializer);
    }

    public function testSetAceIsDeniedWithoutObjectTypeForNonAdmins(): void
    {
        $this->authorizationChecker->method('isGranted')->willReturnCallback(fn (mixed $attribute): bool => 'ROLE_ADMIN' !== $attribute);
        $this->permissionManager->expects($this->never())->method('updateOrCreateAce');

        $this->expectException(AccessDeniedHttpException::class);

        $this->controller->setAce($this->jsonRequest('PUT', [
            'userType' => 'user',
            'userId' => 'u-1',
            'mask' => 1,
        ]), $this->serializer);
    }

    public function testTypeWideAceIsVotedOnAnObjectTypeSubject(): void
    {
        $this->em->expects($this->never())->method('find');
        $this->authorizationChecker
            ->method('isGranted')
            ->willReturnCallback(function (mixed $attribute, mixed $subject = null): bool {
                if ('ROLE_ADMIN' === $attribute) {
                    return false;
                }

                $this->assertSame(SetPermissionVoter::ACL_WRITE, $attribute);
                $this->assertInstanceOf(ObjectTypeSubject::class, $subject);
                $this->assertSame('pub', $subject->objectType);
                $this->assertSame(ObjectMock::class, $subject->className);

                return true;
            });

        $this->permissionManager
            ->expects($this->once())
            ->method('updateOrCreateAce')
            ->with(AccessControlEntryInterface::TYPE_USER_VALUE, 'u-1', 'pub', null, PermissionInterface::VIEW, [])
            ->willReturn($this->createAce());

        $this->controller->setAce($this->jsonRequest('PUT', [
            'userType' => 'user',
            'userId' => 'u-1',
            'objectType' => 'pub',
            'mask' => PermissionInterface::VIEW,
        ]), $this->serializer);
    }

    public function testTypeWideAceIsDeniedWhenNoVoterGrantsIt(): void
    {
        $this->authorizationChecker->method('isGranted')->willReturn(false);
        $this->permissionManager->expects($this->never())->method('updateOrCreateAce');

        $this->expectException(AccessDeniedHttpException::class);

        $this->controller->setAce($this->jsonRequest('PUT', [
            'userType' => 'user',
            'userId' => 'u-1',
            'objectType' => 'pub',
            'mask' => PermissionInterface::VIEW,
        ]), $this->serializer);
    }

    public function testUnknownObjectTypeIsRejected(): void
    {
        $this->authorizationChecker->method('isGranted')->willReturn(false);

        $this->expectException(\InvalidArgumentException::class);

        $this->controller->setAce($this->jsonRequest('PUT', [
            'userType' => 'user',
            'userId' => 'u-1',
            'objectType' => 'asset',
            'objectId' => 'a-1',
            'mask' => PermissionInterface::VIEW,
        ]), $this->serializer);
    }

    public function testInvalidJsonBodyIsRejected(): void
    {
        $this->authorizationChecker->expects($this->never())->method('isGranted');

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Invalid JSON body');

        $request = Request::create('/permissions/ace', 'PUT', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{not json');

        $this->controller->setAce($request, $this->serializer);
    }

    public function testAdminCanDeleteAnAce(): void
    {
        $this->grantOnly('ROLE_ADMIN');

        $this->permissionManager
            ->expects($this->once())
            ->method('deleteAce')
            ->with(AccessControlEntryInterface::TYPE_GROUP_VALUE, 'g-1', 'pub', 'pub-42');

        $response = $this->controller->deleteAce($this->jsonRequest('DELETE', [
            'userType' => 'group',
            'userId' => 'g-1',
            'objectType' => 'pub',
            'objectId' => 'pub-42',
        ]));

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame('true', $response->getContent());
    }

    public function testDeleteAceWithAnEmptyObjectIdTargetsTheWholeType(): void
    {
        $this->grantOnly('ROLE_ADMIN');

        $this->permissionManager
            ->expects($this->once())
            ->method('deleteAce')
            ->with(AccessControlEntryInterface::TYPE_USER_VALUE, 'u-1', 'pub', null);

        $this->controller->deleteAce($this->jsonRequest('DELETE', [
            'userType' => 'user',
            'userId' => 'u-1',
            'objectType' => 'pub',
            'objectId' => '',
        ]));
    }

    public function testDeleteAceIsDeniedForNonOwners(): void
    {
        $this->em->method('find')->willReturn(new ObjectMock('pub-42', 'someone-else'));
        $this->authorizationChecker->method('isGranted')->willReturn(false);
        $this->permissionManager->expects($this->never())->method('deleteAce');

        $this->expectException(AccessDeniedHttpException::class);

        $this->controller->deleteAce($this->jsonRequest('DELETE', $this->userAceBody()));
    }

    public function testIndexAcesSerializesTheMatchingAces(): void
    {
        $this->grantOnly('ROLE_ADMIN');

        $repository = $this->createMock(PermissionRepositoryInterface::class);
        $repository
            ->expects($this->once())
            ->method('findAcesByParams')
            ->with([
                'objectType' => 'pub',
                'objectId' => 'pub-42',
            ])
            ->willReturn([$this->createAce(), $this->createAce()]);

        $response = $this->controller->indexAces(
            Request::create('/permissions/aces', 'GET', ['objectType' => 'pub', 'objectId' => 'pub-42']),
            $repository,
            $this->serializer,
        );

        $payload = json_decode($response->getContent(), true);
        $this->assertCount(2, $payload);
        $this->assertSame('pub', $payload[0]['objectType']);
    }

    public function testIndexAcesConvertsTheUserType(): void
    {
        $this->grantOnly('ROLE_ADMIN');

        $repository = $this->createMock(PermissionRepositoryInterface::class);
        $repository
            ->expects($this->once())
            ->method('findAcesByParams')
            ->with([
                'userType' => AccessControlEntryInterface::TYPE_GROUP_VALUE,
                'userId' => 'g-1',
            ])
            ->willReturn([]);

        $this->controller->indexAces(
            Request::create('/permissions/aces', 'GET', ['userType' => 'group', 'userId' => 'g-1']),
            $repository,
            $this->serializer,
        );
    }

    #[DataProvider('nullQueryValueProvider')]
    public function testIndexAcesTreatsEmptyAndNullStringsAsNull(string $value): void
    {
        $this->grantOnly('ROLE_ADMIN');

        $repository = $this->createMock(PermissionRepositoryInterface::class);
        $repository
            ->expects($this->once())
            ->method('findAcesByParams')
            ->with([
                'objectType' => 'pub',
                'objectId' => null,
            ])
            ->willReturn([]);

        $this->controller->indexAces(
            Request::create('/permissions/aces', 'GET', ['objectType' => 'pub', 'objectId' => $value]),
            $repository,
            $this->serializer,
        );
    }

    public static function nullQueryValueProvider(): iterable
    {
        yield 'empty string' => [''];
        yield 'null string' => ['null'];
    }

    public function testIndexAcesForwardsWildcardFlags(): void
    {
        $this->grantOnly('ROLE_ADMIN');

        $repository = $this->createMock(PermissionRepositoryInterface::class);
        $repository
            ->expects($this->once())
            ->method('findAcesByParams')
            ->with([
                'objectType' => 'pub',
                'objectId' => 'pub-42',
                'userId' => 'u-1',
                'userIdWildcard' => '1',
                'objectIdWildcard' => '1',
            ])
            ->willReturn([]);

        $this->controller->indexAces(
            Request::create('/permissions/aces', 'GET', [
                'objectType' => 'pub',
                'objectId' => 'pub-42',
                'userId' => 'u-1',
                'userIdWildcard' => '1',
                'objectIdWildcard' => '1',
                'ignored' => 'yes',
            ]),
            $repository,
            $this->serializer,
        );
    }

    public function testIndexAcesRejectsAnInvalidUserType(): void
    {
        $this->grantOnly('ROLE_ADMIN');

        $repository = $this->createMock(PermissionRepositoryInterface::class);
        $repository->expects($this->never())->method('findAcesByParams');

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Invalid userType');

        $this->controller->indexAces(
            Request::create('/permissions/aces', 'GET', ['userType' => 'robot']),
            $repository,
            $this->serializer,
        );
    }

    public function testIndexAcesChecksReadAccessOnTheObject(): void
    {
        $object = new ObjectMock('pub-42', 'u-1');
        $this->em->method('find')->with(ObjectMock::class, 'pub-42')->willReturn($object);
        $this->authorizationChecker
            ->method('isGranted')
            ->willReturnCallback(fn (mixed $attribute, mixed $subject = null): bool => SetPermissionVoter::ACL_READ === $attribute && $subject === $object);

        $repository = $this->createMock(PermissionRepositoryInterface::class);
        $repository->expects($this->once())->method('findAcesByParams')->willReturn([]);

        $this->controller->indexAces(
            Request::create('/permissions/aces', 'GET', ['objectType' => 'pub', 'objectId' => 'pub-42']),
            $repository,
            $this->serializer,
        );
    }

    public function testIndexAcesIsDeniedWithoutReadAccess(): void
    {
        $this->em->method('find')->willReturn(new ObjectMock('pub-42', 'someone-else'));
        $this->authorizationChecker->method('isGranted')->willReturn(false);

        $repository = $this->createMock(PermissionRepositoryInterface::class);
        $repository->expects($this->never())->method('findAcesByParams');

        $this->expectException(AccessDeniedHttpException::class);

        $this->controller->indexAces(
            Request::create('/permissions/aces', 'GET', ['objectType' => 'pub', 'objectId' => 'pub-42']),
            $repository,
            $this->serializer,
        );
    }

    private function grantOnly(string $grantedAttribute): void
    {
        $this->authorizationChecker
            ->method('isGranted')
            ->willReturnCallback(fn (mixed $attribute): bool => $attribute === $grantedAttribute);
    }

    private function jsonRequest(string $method, array $body): Request
    {
        return Request::create(
            '/permissions/ace',
            $method,
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($body, JSON_THROW_ON_ERROR),
        );
    }

    private function userAceBody(): array
    {
        return [
            'userType' => 'user',
            'userId' => 'u-1',
            'objectType' => 'pub',
            'objectId' => 'pub-42',
            'mask' => PermissionInterface::VIEW,
        ];
    }

    private function createAce(): AccessControlEntry
    {
        $ace = new AccessControlEntry();
        $ace->setUserId('u-1');
        $ace->setObjectType('pub');
        $ace->setObjectId('pub-42');
        $ace->setMask(PermissionInterface::VIEW | PermissionInterface::EDIT);

        return $ace;
    }
}
