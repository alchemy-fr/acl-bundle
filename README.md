# Alchemy ACL bundle

[![CI](https://github.com/alchemy-fr/acl-bundle/actions/workflows/ci.yaml/badge.svg)](https://github.com/alchemy-fr/acl-bundle/actions/workflows/ci.yaml)
[![Latest Stable Version](https://img.shields.io/packagist/v/alchemy/acl-bundle.svg)](https://packagist.org/packages/alchemy/acl-bundle)
[![PHP Version](https://img.shields.io/packagist/dependency-v/alchemy/acl-bundle/php.svg)](https://packagist.org/packages/alchemy/acl-bundle)
[![License](https://img.shields.io/packagist/l/alchemy/acl-bundle.svg)](#license)

**Per-object permissions for Symfony and Doctrine, in a single table.**

Symfony's roles and voters answer *"is this user an editor?"*. Many applications also need
to answer *"can this user edit **this** document?"*, and to let users share their own
objects with other users or groups. This bundle does exactly that, without the weight of a
full ACL framework: one Doctrine table, one service, one voter.

```php
// Alice shares a publication with the "marketing" group
$permissionManager->grantGroupOnObject($marketingGroupId, $publication, PermissionInterface::VIEW | PermissionInterface::EDIT);

// Anywhere in the application
$this->denyAccessUnlessGranted((string) PermissionInterface::EDIT, $publication);
```

## Features

- **Users, groups and everybody**: grant permissions to a user, to a group, or to all users
  at once.
- **One object or a whole type**: an entry targets a single object or every object of a type
  (*"the editors group can view every publication"*).
- **Ownership out of the box**: the owner of an object is granted everything on it.
- **Bitmask permissions**: 18 predefined permissions (`VIEW`, `EDIT`, `DELETE`, `SHARE`…)
  combined in one integer, checked with a single bitwise operation.
- **Symfony Security integration**: a voter, so `isGranted()`, `#[IsGranted]` and Twig's
  `is_granted()` work on your entities.
- **Doctrine query filtering**: list only the objects a user is allowed to see, with one
  join computed by the database, instead of loading everything and filtering in PHP.
- **Events**: every change dispatches an event with the previous and the new state, ready for
  audit logs or cache invalidation.
- **HTTP API**: list, create, update and delete permissions over JSON, protected by voters
  you can extend.
- **Admin UI**: an optional EasyAdmin page with a checkbox matrix to manage the permissions of
  one object or of a whole type.
- **Automatic cleanup**: the permissions of an object are deleted along with it.
- **Bring your own users**: the bundle does not own your users or groups; two small
  interfaces connect it to any user provider (Doctrine, LDAP, OAuth/OIDC, a remote API…).

## Why this bundle?

| | Roles & voters | Symfony ACL (`symfony/acl-bundle`) | **Alchemy ACL bundle** |
|---|---|---|---|
| Per-object permissions | Hand-written in each voter | Yes | Yes |
| Groups | Hand-written | No (roles only) | Yes |
| Storage | — | Several tables, object identities | One table |
| List the objects a user can see | Hand-written queries | Not provided | `joinAcl()` on any query |
| HTTP API, admin UI, events | — | — | Included |

It fits applications where users own content and share it: document or asset management,
publishing platforms, collaborative tools, multi-tenant back offices.

## Requirements

- PHP 8.5 or later
- Symfony 6.4, 7.4 or 8.x
- Doctrine ORM 2.14+ or 3.6+, with `ramsey/uuid-doctrine`
- Protected entities identified by a UUID
- Optional: EasyAdmin 5 for the admin UI, `symfony/form` for the form types

## Quick start

```bash
composer require alchemy/acl-bundle
```

Declare the entities to protect:

```yaml
# config/packages/alchemy_acl.yaml
alchemy_acl:
    objects:
        publication: App\Entity\Publication
```

Implement `AclObjectInterface` on them and `AclUserInterface` on your security user:

```php
class Publication implements AclObjectInterface
{
    public function getId(): string { return $this->id->toString(); }
    public function getAclOwnerId(): string { return $this->owner->getId(); }
}

class User implements UserInterface, AclUserInterface
{
    public function getId(): string { return $this->id; }
}
```

Tell the bundle where your users and groups are, by aliasing
`UserRepositoryInterface` and `GroupRepositoryInterface` to your services, then create the
ACL table with a Doctrine migration. You can now grant and check permissions.

The full walkthrough is in the [installation guide](doc/installation.md).

## Documentation

1. [Installation and configuration](doc/installation.md)
2. [Concepts: entries, permissions and resolution](doc/concepts.md)
3. [Checking permissions](doc/checking-permissions.md)
4. [Managing permissions from PHP](doc/managing-permissions.md)
5. [Filtering Doctrine queries](doc/query-filtering.md)
6. [Events](doc/events.md)
7. [HTTP API](doc/http-api.md)
8. [Admin UI (EasyAdmin) and form types](doc/admin-ui.md)
9. [Contributing](doc/contributing.md)

## Versioning

The bundle follows [semantic versioning](https://semver.org/). Releases and their notes are
listed on the [GitHub releases page](https://github.com/alchemy-fr/acl-bundle/releases)
and on [Packagist](https://packagist.org/packages/alchemy/acl-bundle).

## Contributing

Bug reports and pull requests are welcome on
[GitHub](https://github.com/alchemy-fr/acl-bundle/issues). See the
[contributing guide](doc/contributing.md) to set up the project and run the tests.

## License

This bundle is released under the MIT license.

Developed and maintained by [Alchemy](https://www.alchemy.fr/).
