# Alchemy ACL bundle

[![CI](https://github.com/alchemy-fr/acl-bundle/actions/workflows/ci.yaml/badge.svg)](https://github.com/alchemy-fr/acl-bundle/actions/workflows/ci.yaml)

Lightweight access control lists for Symfony applications.

The bundle stores **access control entries** (ACEs) in a single Doctrine table. An ACE
grants a bitmask of permissions to a *user* or a *group*, on one object or on every
object of a type. It ships with a Symfony voter, a service to check and manage
permissions, a helper to filter Doctrine queries by ACL, an HTTP API and an optional
EasyAdmin UI.

## Table of contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Making your entities ACL-aware](#making-your-entities-acl-aware)
- [Permissions](#permissions)
- [Checking permissions](#checking-permissions)
- [Managing ACEs from PHP](#managing-aces-from-php)
- [Filtering Doctrine queries](#filtering-doctrine-queries)
- [Events](#events)
- [HTTP API](#http-api)
- [Admin UI (EasyAdmin)](#admin-ui-easyadmin)
- [Development](#development)
- [License](#license)

## Requirements

| Dependency   | Version          |
|--------------|------------------|
| PHP          | 8.5              |
| Symfony      | 6.4 or 7.4       |
| Doctrine ORM | 2.6 or 3.6       |

## Installation

```bash
composer require alchemy/acl-bundle
```

Register the bundle if Flex did not do it for you:

```php
// config/bundles.php
return [
    // ...
    Alchemy\AclBundle\AlchemyAclBundle::class => ['all' => true],
];
```

The `AccessControlEntry` entity uses the `uuid` DBAL type from `ramsey/uuid-doctrine`.
Register it once and let Doctrine's `auto_mapping` discover the entity:

```yaml
# config/packages/doctrine.yaml
doctrine:
    dbal:
        types:
            uuid: Ramsey\Uuid\Doctrine\UuidType
    orm:
        auto_mapping: true
```

Then create the table:

```bash
bin/console doctrine:migrations:diff && bin/console doctrine:migrations:migrate
```

Finally import the API routes (the prefix is up to you):

```yaml
# config/routes/alchemy_acl.yaml
alchemy_acl_api:
    resource: '@AlchemyAclBundle/config/routing/permissions_api.yaml'
    prefix: /permissions
```

## Configuration

### Objects

Declare the entities you want to protect. The key is the `objectType` used everywhere
(database, API, events), the value is the entity class. Subclasses and Doctrine proxies
of a mapped class resolve to the same `objectType`.

```yaml
# config/packages/alchemy_acl.yaml
alchemy_acl:
    objects:
        publication: App\Entity\Publication
        asset: App\Entity\Asset

    # Optional: the permissions offered by the admin UI.
    # Default: [VIEW, CREATE, EDIT, DELETE, OPERATOR, OWNER]
    enabled_permissions: [VIEW, EDIT, DELETE]
```

### User and group repositories

The bundle knows nothing about your users. Provide two services by aliasing the bundle
interfaces to your own implementations:

```yaml
# config/services.yaml
services:
    Alchemy\AclBundle\Repository\UserRepositoryInterface: '@App\Repository\UserRepository'
    Alchemy\AclBundle\Repository\GroupRepositoryInterface: '@App\Repository\GroupRepository'
```

```php
namespace Alchemy\AclBundle\Repository;

interface UserRepositoryInterface
{
    /** @return array<array{id: string, username: string}> */
    public function getUsers(array $options = []): array;

    /** @return array{id: string, username: string}|null */
    public function getUser(string $userId, array $options = []): ?array;

    /** @return string[] The IDs of the groups the user belongs to */
    public function getAclGroupsId(AclUserInterface $user): array;
}

interface GroupRepositoryInterface
{
    /** @return array<array{id: string, name: string}> */
    public function getGroups(array $options = []): array;

    /** @return array{id: string, name: string}|null */
    public function getGroup(string $groupId, array $options = []): ?array;
}
```

`getAclGroupsId()` is called on every permission check, so make it cheap (cache it if
needed). The other methods are only used by the API serializer and the admin UI.

## Making your entities ACL-aware

Protected entities implement `AclObjectInterface`. The owner returned by `getAclOwnerId()`
is granted everything on the object without any ACE.

```php
use Alchemy\AclBundle\AclObjectInterface;

class Publication implements AclObjectInterface
{
    public function getId(): string
    {
        return $this->id->toString();
    }

    public function getAclOwnerId(): string
    {
        return $this->owner->getId();
    }
}
```

Your security user implements `AclUserInterface` in addition to Symfony's `UserInterface`:

```php
use Alchemy\AclBundle\Model\AclUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class User implements UserInterface, AclUserInterface
{
    public function getId(): string
    {
        return $this->id;
    }
}
```

## Permissions

Permissions are bits. An ACE stores their sum in its `mask`. The constants live in
`Alchemy\AclBundle\Security\PermissionInterface`:

| Name             | Value  | Name              | Value   |
|------------------|-------:|-------------------|--------:|
| `VIEW`           | 1      | `CHILD_VIEW`      | 512     |
| `CREATE`         | 2      | `CHILD_CREATE`    | 1024    |
| `EDIT`           | 4      | `CHILD_EDIT`      | 2048    |
| `DELETE`         | 8      | `CHILD_DELETE`    | 4096    |
| `UNDELETE`       | 16     | `CHILD_UNDELETE`  | 8192    |
| `OPERATOR`       | 32     | `CHILD_OPERATOR`  | 16384   |
| `MASTER`         | 64     | `CHILD_MASTER`    | 32768   |
| `OWNER`          | 128    | `CHILD_OWNER`     | 65536   |
| `SHARE`          | 256    | `CHILD_SHARE`     | 131072  |

Permissions are independent: `EDIT` does not imply `VIEW`. A mask of `7` grants
`VIEW`, `CREATE` and `EDIT`. The `CHILD_*` permissions have no built-in behaviour; they
are here for applications that want to express rights on children of an object.

### Wildcards

- **All users**: an ACE with a `NULL` `userId` applies to everybody. Through the API and
  the admin UI, send `AccessControlEntryInterface::USER_WILDCARD` (`__ALL_USERS__`) as the
  `userId`.
- **All objects of a type**: an ACE with a `NULL` `objectId` applies to every object of
  its `objectType`.

### How a check is resolved

For a user, an object and a permission, access is granted if any of the following holds:

1. the user is the owner of the object (see `isGranted(..., ownershipGrants: false)` to
   disable this);
2. an ACE carrying the permission targets the user, one of the user's groups, or all users,
   on this object or on the whole object type.

## Checking permissions

### With the Symfony voter

`AclVoter` votes on any `AclObjectInterface` when the attribute is a numeric permission:

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

### With the `PermissionManager`

```php
use Alchemy\AclBundle\Security\PermissionManager;
use Alchemy\AclBundle\Security\PermissionInterface;

public function __construct(private PermissionManager $permissionManager) {}

// One permission
$this->permissionManager->isGranted($user, $publication, PermissionInterface::EDIT);

// Any of several permissions
$this->permissionManager->isGranted($user, $publication, [PermissionInterface::EDIT, PermissionInterface::OPERATOR]);

// Ignore ownership, rely on ACEs only
$this->permissionManager->isGranted($user, $publication, PermissionInterface::EDIT, ownershipGrants: false);

// Who can see this object?
$userIds = $this->permissionManager->getAllowedUsers($publication, PermissionInterface::VIEW);
$groupIds = $this->permissionManager->getAllowedGroups($publication, PermissionInterface::VIEW);
```

The manager caches the ACEs it loads per user and object for the lifetime of the request.
Writing through the manager invalidates the relevant entry; call `resetCache()` if you
change ACEs by other means (long-running workers, direct SQL...).

## Managing ACEs from PHP

```php
use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AclBundle\Security\PermissionInterface;

// Grant a user or a group on one object (replaces the existing mask)
$permissionManager->grantUserOnObject($userId, $publication, PermissionInterface::VIEW | PermissionInterface::EDIT);
$permissionManager->grantGroupOnObject($groupId, $publication, PermissionInterface::VIEW);

// Full control: object type wide, append to the existing mask, attach metadata
$permissionManager->updateOrCreateAce(
    AccessControlEntryInterface::TYPE_GROUP_VALUE,
    $groupId,
    'publication',
    null,                        // every publication
    PermissionInterface::VIEW,
    metadata: ['granted_by' => $adminId],
    append: true,                // keep the bits already set
);

// Remove an ACE
$permissionManager->deleteAce(AccessControlEntryInterface::TYPE_USER_VALUE, $userId, 'publication', $publication->getId());

// Read
$permissionManager->getAces($user, $publication);      // ACEs that apply to this user on this object
$permissionManager->getObjectAces($publication);       // every ACE on this object, plus type-wide ones
```

An optional `parentId` lets you keep several ACEs for the same user and object, one per
"source" (for instance the collection the object was shared through). The unique
constraint is on `(userType, userId, objectType, objectId, parentId)`.

### Automatic cleanup

`AclObjectDeleteListener` listens to Doctrine's `postRemove` event and deletes every ACE
of a mapped object when the object is removed.

## Filtering Doctrine queries

To list only the objects a user can see, join the ACL table with the static helper:

```php
use Alchemy\AclBundle\Entity\AccessControlEntryRepository;
use Alchemy\AclBundle\Security\PermissionInterface;

$qb = $this->createQueryBuilder('p');

AccessControlEntryRepository::joinAcl(
    $qb,
    $user->getId(),
    $userRepository->getAclGroupsId($user),
    'publication',              // objectType
    'p',                        // alias of the protected entity in the query
    PermissionInterface::VIEW,
);
```

The helper adds an `INNER JOIN` (pass `inner: false` for a `LEFT JOIN`) that matches ACEs
on the object or on the whole type, for the user, their groups or all users, and having
the requested permission bits. The `aceAlias` and `paramPrefix` arguments let you join the
ACL table several times in one query.

## Events

Every write through the `PermissionManager` (including the HTTP API) dispatches an event
with the previous state of the ACE, so you can audit changes or refresh caches:

| Event                                   | Name         | Extra getters                                         |
|-----------------------------------------|--------------|-------------------------------------------------------|
| `Alchemy\AclBundle\Event\AclUpsertEvent` | `acl.upsert` | `getPermissions()`, `getMetadata()`                   |
| `Alchemy\AclBundle\Event\AclDeleteEvent` | `acl.delete` |                                                       |

Both expose `getUserType()`, `getUserId()`, `getObjectType()`, `getObjectId()`,
`getPreviousPermissions()` and `getPreviousMetadata()`. The previous values are `null`
when the ACE did not exist before.

```php
use Alchemy\AclBundle\Event\AclUpsertEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: AclUpsertEvent::NAME)]
public function onAclUpsert(AclUpsertEvent $event): void
{
    // ...
}
```

## HTTP API

The examples below assume the routes were imported under `/permissions`.

### Fields

| Field        | Description                                                                                  |
|--------------|----------------------------------------------------------------------------------------------|
| `userType`   | `user` or `group`                                                                            |
| `userId`     | The user or group ID. Send `__ALL_USERS__` to target everybody (stored as `NULL`).           |
| `objectType` | One of the keys declared in `alchemy_acl.objects`                                            |
| `objectId`   | The object ID. `null` or empty targets every object of the type.                             |
| `mask`       | The sum of the granted permission bits                                                       |
| `metadata`   | Free JSON object stored with the ACE (optional)                                              |
| `parentId`   | Optional discriminator, see [Managing ACEs from PHP](#managing-aces-from-php)                |

Responses are serialized ACEs. When the ACE targets a specific user or group, the payload
also contains a `user` or `group` key filled by your repositories.

### `GET /permissions/aces`

Lists ACEs. Filters are passed as query parameters: `userType`, `userId`, `objectType`,
`objectId`. An empty value or the string `null` matches `NULL` (for instance
`objectId=null` returns type-wide ACEs). Add `userIdWildcard=1` or `objectIdWildcard=1` to
also include the wildcard ACEs alongside the ones matching the given ID.

```bash
# ACEs of an object, including type-wide ones
curl "{HOST}/permissions/aces?objectType=publication&objectId=pub-42&objectIdWildcard=1"

# ACEs of a group
curl "{HOST}/permissions/aces?userType=group&userId=g-42"

# ACEs of a user on an object
curl "{HOST}/permissions/aces?userType=user&userId=u-42&objectType=publication&objectId=pub-42"
```

### `PUT /permissions/ace`

Creates or replaces an ACE. The body can be JSON or form-encoded.

```json
{
    "userType": "user",
    "userId": "u-42",
    "objectType": "publication",
    "objectId": "pub-42",
    "mask": 7,
    "metadata": {"granted_by": "admin"}
}
```

### `DELETE /permissions/ace`

```json
{
    "userType": "user",
    "userId": "u-42",
    "objectType": "publication",
    "objectId": "pub-42"
}
```

### Authorization

- `ROLE_ADMIN` can do everything.
- Otherwise the application's voters are asked for `ACL_READ` (list) or `ACL_WRITE`
  (create, update, delete). The bundled `SetPermissionVoter` grants both to the owner of
  the object.
- When `objectId` is empty there is no object to vote on: the subject is an
  `Alchemy\AclBundle\Security\ObjectTypeSubject` carrying the `objectType` and its class.
  The bundle grants nothing on it by default, so write a voter if non-admins must manage
  type-wide ACEs:

```php
use Alchemy\AclBundle\Security\ObjectTypeSubject;
use Alchemy\AclBundle\Security\Voter\SetPermissionVoter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ObjectTypeAclVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return SetPermissionVoter::ACL_WRITE === $attribute && $subject instanceof ObjectTypeSubject;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return 'publication' === $subject->objectType
            && in_array('ROLE_PUBLICATION_MANAGER', $token->getRoleNames(), true);
    }
}
```

Requests with an unknown `objectType` or an invalid `userType` get a `400`, denied requests
a `403`.

## Admin UI (EasyAdmin)

When `EasyAdminBundle` is installed the bundle prepends an `AccessControlEntry` entity to
the EasyAdmin configuration, so ACEs can be browsed and edited like any other entity.

The bundle also ships templates to manage the permissions of one object or of a whole
type with a checkbox matrix (`@AlchemyAcl/permissions/entity/acl.html.twig` and
`@AlchemyAcl/permissions/global/acl.html.twig`). They call the API through the admin
routes, which you import next to your admin area:

```yaml
# config/routes/alchemy_acl_admin.yaml
alchemy_acl_admin:
    resource: '@AlchemyAclBundle/config/routing/permissions_admin.yaml'
    prefix: /admin/permissions
```

Render them from your own admin controller with the parameters built by `PermissionView`:

```php
use Alchemy\AclBundle\Admin\PermissionView;

public function permissions(PermissionView $view, string $type, ?string $id = null): Response
{
    return $this->render(
        null === $id ? '@AlchemyAcl/permissions/global/acl.html.twig' : '@AlchemyAcl/permissions/entity/acl.html.twig',
        $view->getViewParameters($type, $id),
    );
}
```

The checkboxes shown are the ones listed in `alchemy_acl.enabled_permissions`.

## Development

Install the dependencies and run the test suite:

```bash
composer install
composer test
```

Code style and refactoring rules:

```bash
composer cs      # php-cs-fixer
composer rector  # rector
```

Without a local PHP 8.5, the same commands run in Docker:

```bash
docker run --rm -v "$PWD":/app -w /app php:8.5-cli vendor/bin/phpunit
```

The suite is made of unit tests only: the Doctrine repository, the entity manager and the
security layer are replaced by mocks, so no database is needed.

## License

MIT
