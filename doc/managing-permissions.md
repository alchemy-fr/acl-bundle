# Managing permissions from PHP

- [Granting](#granting)
- [Full control: `updateOrCreateAce()`](#full-control-updateorcreateace)
- [Revoking](#revoking)
- [Reading](#reading)
- [Automatic cleanup](#automatic-cleanup)

Every write goes through the `PermissionManager`, which clears its cache and dispatches an
[event](events.md).

## Granting

```php
use Alchemy\AclBundle\Security\PermissionInterface;

// Grant a user or a group on one object (replaces the existing mask)
$permissionManager->grantUserOnObject($userId, $publication, PermissionInterface::VIEW | PermissionInterface::EDIT);
$permissionManager->grantGroupOnObject($groupId, $publication, PermissionInterface::VIEW);
```

Both accept a `parentId` as fourth argument (see [Concepts](concepts.md#several-entries-for-the-same-pair-parentid)).

## Full control: `updateOrCreateAce()`

```php
use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AclBundle\Security\PermissionInterface;

$permissionManager->updateOrCreateAce(
    AccessControlEntryInterface::TYPE_GROUP_VALUE,
    $groupId,
    'publication',
    null,                                  // every publication
    PermissionInterface::VIEW,
    metadata: ['granted_by' => $adminId],  // null keeps the metadata of an existing ACE
    parentId: null,
    append: true,                          // keep the bits already set
);
```

- Pass `AccessControlEntryInterface::USER_WILDCARD` as the user ID to target all users.
- Pass `null` as the object ID to target every object of the type.
- Without `append`, the given mask replaces the existing one.
- `metadata` defaults to `[]`, which erases the metadata of an existing ACE; pass `null` to
  keep them.

## Revoking

```php
// Remove an ACE
$permissionManager->deleteAce(AccessControlEntryInterface::TYPE_USER_VALUE, $userId, 'publication', $publication->getId());
```

To remove some bits only, update the ACE with the new mask.

## Reading

```php
// ACEs that apply to this user on this object (own, groups, all users, type-wide)
$permissionManager->getAces($user, $publication);

// Every ACE on this object, plus the type-wide ones
$permissionManager->getObjectAces($publication);

// One ACE, or every ACE of a user on an object whatever its parentId
$permissionManager->findAce(AccessControlEntryInterface::TYPE_USER_VALUE, $userId, 'publication', $publication->getId());
$permissionManager->findAces(AccessControlEntryInterface::TYPE_USER_VALUE, $userId, 'publication', $publication->getId());
```

ACEs implement `AccessControlEntryInterface`, with helpers such as `hasPermission()`,
`getPermissions()` (the list of granted bits) and `getMetadata()`.

## Automatic cleanup

`AclObjectDeleteListener` deletes every ACE of a mapped object once Doctrine has removed the
object (the object ID is read on `preRemove`, the ACEs are deleted on `postRemove`). Objects
deleted with DQL or SQL bypass Doctrine events: delete their ACEs yourself.

---

Previous: [Checking permissions](checking-permissions.md) · Next: [Filtering Doctrine queries](query-filtering.md)
