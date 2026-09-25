<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Tests\Security;

use Alchemy\AclBundle\Security\ObjectTypeSubject;
use Alchemy\AclBundle\Tests\Mock\ObjectMock;
use PHPUnit\Framework\TestCase;

class ObjectTypeSubjectTest extends TestCase
{
    public function testExposesTheTypeAndItsClass(): void
    {
        $subject = new ObjectTypeSubject('pub', ObjectMock::class);

        $this->assertSame('pub', $subject->objectType);
        $this->assertSame(ObjectMock::class, $subject->className);
    }

    public function testIsImmutable(): void
    {
        $subject = new ObjectTypeSubject('pub', ObjectMock::class);

        $this->expectException(\Error::class);

        $subject->objectType = 'asset';
    }
}
