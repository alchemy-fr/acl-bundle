<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Tests\Security\Voter;

use Alchemy\AclBundle\AclObjectInterface;
use Alchemy\AclBundle\Security\ObjectTypeSubject;
use Alchemy\AclBundle\Security\Voter\SetPermissionVoter;
use Alchemy\AclBundle\Tests\Mock\ObjectMock;
use Alchemy\AclBundle\Tests\Mock\SecurityUserMock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class SetPermissionVoterTest extends TestCase
{
    private Security&MockObject $security;
    private SetPermissionVoter $voter;

    protected function setUp(): void
    {
        $this->security = $this->createMock(Security::class);
        $this->voter = new SetPermissionVoter($this->security);
    }

    public function testSupportsAclAttributesOnly(): void
    {
        $this->assertTrue($this->voter->supportsAttribute(SetPermissionVoter::ACL_READ));
        $this->assertTrue($this->voter->supportsAttribute(SetPermissionVoter::ACL_WRITE));
        $this->assertFalse($this->voter->supportsAttribute('ROLE_ADMIN'));
        $this->assertFalse($this->voter->supportsAttribute('1'));
    }

    public function testSupportsAclObjectsOnly(): void
    {
        $this->assertTrue($this->voter->supportsType(ObjectMock::class));
        $this->assertTrue($this->voter->supportsType(AclObjectInterface::class));
        $this->assertFalse($this->voter->supportsType(\stdClass::class));
        $this->assertFalse($this->voter->supportsType(ObjectTypeSubject::class));
    }

    #[DataProvider('attributeProvider')]
    public function testAdminIsAlwaysGranted(string $attribute): void
    {
        $this->security->method('isGranted')->with('ROLE_ADMIN')->willReturn(true);

        $token = $this->createToken($this->createMock(UserInterface::class));

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, new ObjectMock('42', 'someone-else'), [$attribute]));
    }

    #[DataProvider('attributeProvider')]
    public function testOwnerIsGranted(string $attribute): void
    {
        $this->security->method('isGranted')->willReturn(false);

        $token = $this->createToken(new SecurityUserMock('u-1'));

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, new ObjectMock('42', 'u-1'), [$attribute]));
    }

    #[DataProvider('attributeProvider')]
    public function testNonOwnerIsDenied(string $attribute): void
    {
        $this->security->method('isGranted')->willReturn(false);

        $token = $this->createToken(new SecurityUserMock('u-1'));

        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, new ObjectMock('42', 'u-2'), [$attribute]));
    }

    #[DataProvider('attributeProvider')]
    public function testObjectWithoutOwnerDeniesEveryone(string $attribute): void
    {
        $this->security->method('isGranted')->willReturn(false);

        $token = $this->createToken(new SecurityUserMock('u-1'));

        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, new ObjectMock('42'), [$attribute]));
    }

    public function testNonAclUsersAreDenied(): void
    {
        $this->security->method('isGranted')->willReturn(false);

        $token = $this->createToken($this->createMock(UserInterface::class));

        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, new ObjectMock('42', 'u-1'), [SetPermissionVoter::ACL_READ]));
    }

    public function testAbstainsOnOtherAttributes(): void
    {
        $token = $this->createToken(new SecurityUserMock('u-1'));

        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $this->voter->vote($token, new ObjectMock('42', 'u-1'), ['1', 'ROLE_ADMIN']));
    }

    public function testAbstainsOnObjectTypeSubjects(): void
    {
        $token = $this->createToken(new SecurityUserMock('u-1'));
        $subject = new ObjectTypeSubject('pub', ObjectMock::class);

        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $this->voter->vote($token, $subject, [SetPermissionVoter::ACL_WRITE]));
    }

    public static function attributeProvider(): iterable
    {
        yield 'read' => [SetPermissionVoter::ACL_READ];
        yield 'write' => [SetPermissionVoter::ACL_WRITE];
    }

    private function createToken(?object $user): TokenInterface
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }
}
