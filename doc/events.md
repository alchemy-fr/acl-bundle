# Events

Every write through the `PermissionManager` (including the [HTTP API](http-api.md) and the
[admin UI](admin-ui.md)) dispatches an event with the previous state of the ACE, so you can
audit changes, notify users or refresh caches.

| Event                                    | Name         | Extra getters                       |
|------------------------------------------|--------------|-------------------------------------|
| `Alchemy\AclBundle\Event\AclUpsertEvent` | `acl.upsert` | `getPermissions()`, `getMetadata()` |
| `Alchemy\AclBundle\Event\AclDeleteEvent` | `acl.delete` |                                     |

Both expose `getUserType()`, `getUserId()`, `getObjectType()`, `getObjectId()`,
`getPreviousPermissions()` and `getPreviousMetadata()`. The previous values are `null` when
the ACE did not exist before. `getPermissions()` and `getMetadata()` are those the ACE holds
after the write, also when `append: true` only added bits.

`acl.delete` is only dispatched when an ACE was actually deleted. The ACEs removed by the
[automatic cleanup](managing-permissions.md#automatic-cleanup) of a deleted object do not
dispatch it.

## Example: audit log

```php
use Alchemy\AclBundle\Event\AclUpsertEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final class AclAuditListener
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    #[AsEventListener(event: AclUpsertEvent::NAME)]
    public function onAclUpsert(AclUpsertEvent $event): void
    {
        $this->logger->info('Permissions changed', [
            'object' => $event->getObjectType().':'.$event->getObjectId(),
            'user' => $event->getUserId(),
            'from' => $event->getPreviousPermissions(),
            'to' => $event->getPermissions(),
        ]);
    }
}
```

---

Previous: [Filtering Doctrine queries](query-filtering.md) · Next: [HTTP API](http-api.md)
