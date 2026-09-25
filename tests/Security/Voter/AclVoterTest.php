<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Tests\Security\Voter;

use Alchemy\AclBundle\AclObjectInterface;
use Alchemy\AclBundle\Security\ObjectTypeSubject;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AclBundle\Security\PermissionManager;
use Alchemy\AclBundle\Security\Voter\AclVoter;
use Alchemy\AclBundle\Tests\Mock\ObjectMock;
use Alchemy\AclBundle\Tests\Mock\SecurityUserMock;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class AclVoterTest extends TestCase
{
    private PermissionManager&MockObject $permissionManager;
    private AclVoter $voter;

    protected function setUp(): void
    {
        $this->permissionManager = $this->createMock(PermissionManager::class);
        $this->voter = new AclVoter($this->permissionManager);
    }

    public function testSupportsNumericAttributesOnly(): void
    {
        $this->assertTrue($this->voter->supportsAttribute('1'));
        $this->assertTrue($this->voter->supportsAttribute((string) PermissionInterface::CHILD_SHARE));
        $this->assertFalse($this->voter->supportsAttribute('ROLE_ADMIN'));
        $this->assertFalse($this->voter->supportsAttribute('ACL_READ'));
    }

    public function testSupportsAclObjectsOnly(): void
    {
        $this->assertTrue($this->voter->supportsType(ObjectMock::class));
        $this->assertTrue($this->voter->supportsType(AclObjectInterface::class));
        $this->assertFalse($this->voter->supportsType(\stdClass::class));
        $this->assertFalse($this->voter->supportsType(ObjectTypeSubject::class));
    }

    public function testGrantsWhenThePermissionManagerGrants(): void
    {
        $user = new SecurityUserMock('u-1');
        $object = new ObjectMock('42');

        $this->permissionManager
            ->expects($this->once())
            ->method('isGranted')
            ->with($user, $object, PermissionInterface::EDIT)
            ->willReturn(true);

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->createToken($user), $object, [(string) PermissionInterface::EDIT])
        );
    }

    public function testDeniesWhenThePermissionManagerDenies(): void
    {
        $user = new SecurityUserMock('u-1');
        $object = new ObjectMock('42');

        $this->permissionManager->method('isGranted')->willReturn(false);

        $this->assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->createToken($user), $object, [(string) PermissionInterface::EDIT])
        );
    }

    public function testDeniesUsersThatAreNotAclUsers(): void
    {
        $this->permissionManager->expects($this->never())->method('isGranted');

        $this->assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->createToken($this->createMock(UserInterface::class)), new ObjectMock('42'), ['1'])
        );
    }

    public function testDeniesAnonymousTokens(): void
    {
        $this->permissionManager->expects($this->never())->method('isGranted');

        $this->assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->createToken(null), new ObjectMock('42'), ['1'])
        );
    }

    public function testAbstainsOnNonNumericAttributes(): void
    {
        $this->permissionManager->expects($this->never())->method('isGranted');

        $this->assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $this->voter->vote($this->createToken(new SecurityUserMock('u-1')), new ObjectMock('42'), ['ROLE_ADMIN'])
        );
    }

    public function testAbstainsOnNonAclSubjects(): void
    {
        $this->permissionManager->expects($this->never())->method('isGranted');

        $this->assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $this->voter->vote($this->createToken(new SecurityUserMock('u-1')), new \stdClass(), ['1'])
        );
        $this->assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $this->voter->vote($this->createToken(new SecurityUserMock('u-1')), null, ['1'])
        );
    }

    private function createToken(?object $user): TokenInterface
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }
}
