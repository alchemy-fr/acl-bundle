<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Tests\Event;

use Alchemy\AclBundle\Event\AclDeleteEvent;
use Alchemy\AclBundle\Event\AclEvent;
use Alchemy\AclBundle\Event\AclUpsertEvent;
use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AclBundle\Security\PermissionInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\Event;

class AclEventTest extends TestCase
{
    public function testEventNamesAreDistinct(): void
    {
        $this->assertSame('acl.upsert', AclUpsertEvent::NAME);
        $this->assertSame('acl.delete', AclDeleteEvent::NAME);
        $this->assertNotSame(AclUpsertEvent::NAME, AclDeleteEvent::NAME);
    }

    public function testUpsertEvent(): void
    {
        $event = new AclUpsertEvent(
            AccessControlEntryInterface::TYPE_GROUP_VALUE,
            'g-1',
            'publication',
            'pub-42',
            PermissionInterface::VIEW | PermissionInterface::EDIT,
            ['reason' => 'shared'],
            PermissionInterface::VIEW,
            ['reason' => 'initial'],
        );

        $this->assertInstanceOf(AclEvent::class, $event);
        $this->assertInstanceOf(Event::class, $event);
        $this->assertSame(AccessControlEntryInterface::TYPE_GROUP_VALUE, $event->getUserType());
        $this->assertSame('g-1', $event->getUserId());
        $this->assertSame('publication', $event->getObjectType());
        $this->assertSame('pub-42', $event->getObjectId());
        $this->assertSame(PermissionInterface::VIEW | PermissionInterface::EDIT, $event->getPermissions());
        $this->assertSame(['reason' => 'shared'], $event->getMetadata());
        $this->assertSame(PermissionInterface::VIEW, $event->getPreviousPermissions());
        $this->assertSame(['reason' => 'initial'], $event->getPreviousMetadata());
    }

    public function testUpsertEventOfANewAceHasNoPreviousState(): void
    {
        $event = new AclUpsertEvent(
            AccessControlEntryInterface::TYPE_USER_VALUE,
            null,
            'publication',
            null,
            PermissionInterface::VIEW,
            [],
            null,
            null,
        );

        $this->assertNull($event->getUserId());
        $this->assertNull($event->getObjectId());
        $this->assertNull($event->getPreviousPermissions());
        $this->assertNull($event->getPreviousMetadata());
    }

    public function testDeleteEvent(): void
    {
        $event = new AclDeleteEvent(
            AccessControlEntryInterface::TYPE_USER_VALUE,
            'u-1',
            'asset',
            'asset-7',
            PermissionInterface::DELETE,
            ['k' => 'v'],
        );

        $this->assertInstanceOf(AclEvent::class, $event);
        $this->assertSame(AccessControlEntryInterface::TYPE_USER_VALUE, $event->getUserType());
        $this->assertSame('u-1', $event->getUserId());
        $this->assertSame('asset', $event->getObjectType());
        $this->assertSame('asset-7', $event->getObjectId());
        $this->assertSame(PermissionInterface::DELETE, $event->getPreviousPermissions());
        $this->assertSame(['k' => 'v'], $event->getPreviousMetadata());
    }
}
