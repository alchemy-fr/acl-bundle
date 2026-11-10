# Checking permissions

- [With the Symfony voter](#with-the-symfony-voter)
- [With the `PermissionManager`](#with-the-permissionmanager)
- [Who can access an object?](#who-can-access-an-object)
- [Caching](#caching)

## With the Symfony voter

`AclVoter` votes on any `AclObjectInterface` when the attribute is a numeric permission. It
plugs ACL checks into everything Symfony Security offers:

```php
use Alchemy\AclBundle\Security\PermissionInterface;

// In a controller
$this->denyAccessUnlessGranted((string) PermissionInterface::EDIT, $publication);

// With the attribute
#[IsGranted('4', subject: 'publication')]
public function edit(Publication $publication): Response
```

```twig
{% if is_granted('4', publication) %}
    <a href="{{ path('publication_edit', {id: publication.id}) }}">Edit</a>
{% endif %}
```

The voter abstains when the subject is not an `AclObjectInterface` or when the attribute is
not numeric, so it lives alongside your own voters. It denies access when the authenticated
user does not implement `AclUserInterface`.

## With the `PermissionManager`

The `PermissionManager` service checks permissions for any user, not only the authenticated
one (in a console command, a Messenger handler…):

```php
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AclBundle\Security\PermissionManager;

public function __construct(private PermissionManager $permissionManager) {}

// One permission
$this->permissionManager->isGranted($user, $publication, PermissionInterface::EDIT);

// Several bits at once: one ACE must carry all of them
$this->permissionManager->isGranted($user, $publication, PermissionInterface::VIEW | PermissionInterface::EDIT);

// Any of several permissions
$this->permissionManager->isGranted($user, $publication, [PermissionInterface::EDIT, PermissionInterface::OPERATOR]);

// Ignore ownership, rely on ACEs only
$this->permissionManager->isGranted($user, $publication, PermissionInterface::EDIT, ownershipGrants: false);
```

`getAces($user, $object)` returns the ACEs that apply to a user on an object (their own, their
groups', the all-users ones, on the object or type-wide), if you need more than a yes or no.

## Who can access an object?

```php
$userIds = $this->permissionManager->getAllowedUsers($publication, PermissionInterface::VIEW);
$groupIds = $this->permissionManager->getAllowedGroups($publication, PermissionInterface::VIEW);
```

Both take type-wide ACEs into account. A `null` in a list stands for an all-users ACE. The
owner is not included: it is granted by `getAclOwnerId()`, not by an ACE.

## Caching

The manager caches the ACEs it loads per user and object for the lifetime of the request.
Writing through the manager clears the whole cache, and the manager is reset between two
requests or two Messenger messages (`kernel.reset`). Call `resetCache()` if you change ACEs by
other means (direct SQL, another process…) during a request or a message.

---

Previous: [Concepts](concepts.md) · Next: [Managing permissions from PHP](managing-permissions.md)
