# Installation and configuration

- [Install the package](#install-the-package)
- [Register the UUID type and the entity](#register-the-uuid-type-and-the-entity)
- [Declare the protected objects](#declare-the-protected-objects)
- [Connect your users and groups](#connect-your-users-and-groups)
- [Make your entities ACL-aware](#make-your-entities-acl-aware)
- [Import the routes](#import-the-routes)
- [Configuration reference](#configuration-reference)

## Install the package

```bash
composer require alchemy/acl-bundle
```

Register the bundle if Symfony Flex did not do it for you:

```php
// config/bundles.php
return [
    // ...
    Alchemy\AclBundle\AlchemyAclBundle::class => ['all' => true],
];
```

## Register the UUID type and the entity

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
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

## Declare the protected objects

Declare the entities you want to protect. The key is the **object type** used everywhere
(database, API, events); the value is the entity class. Subclasses and Doctrine proxies of a
mapped class resolve to the same object type.

```yaml
# config/packages/alchemy_acl.yaml
alchemy_acl:
    objects:
        publication: App\Entity\Publication
        asset: App\Entity\Asset
```

Object types are stored in a 20-character column: keep the keys short.

## Connect your users and groups

The bundle knows nothing about your users and groups: they may live in Doctrine, in an LDAP
directory or behind an identity provider. Provide two services by aliasing the bundle
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

A minimal Doctrine-based implementation:

```php
namespace App\Repository;

use Alchemy\AclBundle\Model\AclUserInterface;
use Alchemy\AclBundle\Repository\UserRepositoryInterface;
use App\Entity\User;

final class AclUserRepository implements UserRepositoryInterface
{
    public function __construct(private UserRepository $users)
    {
    }

    public function getUsers(array $options = []): array
    {
        return array_map($this->normalize(...), $this->users->findBy([], ['username' => 'ASC'], 100));
    }

    public function getUser(string $userId, array $options = []): ?array
    {
        $user = $this->users->find($userId);

        return $user ? $this->normalize($user) : null;
    }

    public function getAclGroupsId(AclUserInterface $user): array
    {
        if (!$user instanceof User) {
            return [];
        }

        return array_map(fn ($group): string => $group->getId(), $user->getGroups()->toArray());
    }

    private function normalize(User $user): array
    {
        return ['id' => $user->getId(), 'username' => $user->getUsername()];
    }
}
```

User and group IDs are stored as strings of at most 36 characters.

## Make your entities ACL-aware

Protected entities implement `AclObjectInterface`. Their `getId()` must return a UUID: the
ACL table stores the object ID in a `uuid` column (a native `UUID` on PostgreSQL), which lets
[`joinAcl()`](query-filtering.md) compare it with the ID of the entity. The owner returned by
`getAclOwnerId()` is granted everything on the object without any entry.

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

## Import the routes

The [HTTP API](http-api.md) is optional. To expose it, import its routes (the prefix is up to
you):

```yaml
# config/routes/alchemy_acl.yaml
alchemy_acl_api:
    resource: '@AlchemyAclBundle/config/routing/permissions_api.yaml'
    prefix: /permissions
```

The [admin UI](admin-ui.md) has its own routes.

## Configuration reference

```yaml
# config/packages/alchemy_acl.yaml
alchemy_acl:
    # The protected entities: object type => entity class
    objects:
        publication: App\Entity\Publication

    # The permissions offered by the admin UI.
    # Default: [VIEW, CREATE, EDIT, DELETE, OPERATOR, OWNER]
    enabled_permissions: [VIEW, EDIT, DELETE]
```

---

Next: [Concepts](concepts.md)
