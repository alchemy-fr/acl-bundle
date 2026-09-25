<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Tests\Mapping;

use Alchemy\AclBundle\Mapping\ObjectMapping;
use Alchemy\AclBundle\Tests\Mock\ChildObjectMock;
use Alchemy\AclBundle\Tests\Mock\ObjectMock;
use Alchemy\AclBundle\Tests\Mock\UnmappedObjectMock;
use PHPUnit\Framework\TestCase;

class ObjectMappingTest extends TestCase
{
    private ObjectMapping $mapping;

    protected function setUp(): void
    {
        $this->mapping = new ObjectMapping([
            'pub' => ObjectMock::class,
            'other' => \stdClass::class,
        ]);
    }

    public function testGetObjectTypes(): void
    {
        $this->assertSame(['pub', 'other'], $this->mapping->getObjectTypes());
    }

    public function testGetClassName(): void
    {
        $this->assertSame(ObjectMock::class, $this->mapping->getClassName('pub'));
    }

    public function testGetClassNameWithUnknownKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Undefined object "asset" in the object mapping');

        $this->mapping->getClassName('asset');
    }

    public function testGetObjectKeyFromInstance(): void
    {
        $this->assertSame('pub', $this->mapping->getObjectKey(new ObjectMock('42')));
    }

    public function testGetObjectKeyFromClassName(): void
    {
        $this->assertSame('pub', $this->mapping->getObjectKey(ObjectMock::class));
    }

    public function testGetObjectKeyResolvesParentClasses(): void
    {
        $this->assertSame('pub', $this->mapping->getObjectKey(new ChildObjectMock('42')));
        $this->assertSame('pub', $this->mapping->getObjectKey(ChildObjectMock::class));
    }

    public function testGetObjectKeyResolvesDoctrineProxies(): void
    {
        $proxyClass = 'Proxies\__CG__\\'.ObjectMock::class;

        $this->assertSame('pub', $this->mapping->getObjectKey($proxyClass));
    }

    public function testGetObjectKeyWithUnmappedClass(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('Class "%s" is not defined in the object mapping', UnmappedObjectMock::class));

        $this->mapping->getObjectKey(new UnmappedObjectMock());
    }

    public function testIsObjectMapped(): void
    {
        $this->assertTrue($this->mapping->isObjectMapped(new ObjectMock('42')));
        $this->assertTrue($this->mapping->isObjectMapped(ObjectMock::class));
        $this->assertTrue($this->mapping->isObjectMapped(new ChildObjectMock('42')));
        $this->assertTrue($this->mapping->isObjectMapped('Proxies\__CG__\\'.ChildObjectMock::class));
        $this->assertFalse($this->mapping->isObjectMapped(new UnmappedObjectMock()));
        $this->assertFalse($this->mapping->isObjectMapped(UnmappedObjectMock::class));
    }

    public function testEmptyMapping(): void
    {
        $mapping = new ObjectMapping([]);

        $this->assertSame([], $mapping->getObjectTypes());
        $this->assertFalse($mapping->isObjectMapped(new ObjectMock('42')));
    }
}
