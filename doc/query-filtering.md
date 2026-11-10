# Filtering Doctrine queries

Checking objects one by one does not scale to lists. To fetch only the objects a user can
see, join the ACL table to your query with the static helper:

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

The helper adds an `INNER JOIN` that matches ACEs:

- on the object or on the whole type,
- for the user, one of their groups or all users,
- carrying all the requested permission bits.

## Options

| Argument      | Default | Description                                                          |
|---------------|---------|----------------------------------------------------------------------|
| `inner`       | `true`  | `false` adds a `LEFT JOIN`, to combine the ACL with other conditions |
| `aceAlias`    | `ace`   | The alias of the joined ACE                                          |
| `paramPrefix` | `''`    | A prefix for the query parameters                                    |

`aceAlias` and `paramPrefix` let you join the ACL table several times in one query.

## Owners and duplicates

`joinAcl()` only looks at ACEs: ownership is not part of the join. To also list the objects
the user owns, use a `LEFT JOIN` and an `OR`:

```php
AccessControlEntryRepository::joinAcl($qb, $userId, $groupIds, 'publication', 'p', PermissionInterface::VIEW, inner: false);

$qb
    ->andWhere('p.owner = :owner OR ace.id IS NOT NULL')
    ->setParameter('owner', $userId);
```

When several ACEs match the same object (a user ACE and a group ACE, for instance), the
object comes back once per ACE. Add `$qb->distinct()` or group by the object ID if that
matters, and use Doctrine's `Paginator` to paginate such a query.

---

Previous: [Managing permissions from PHP](managing-permissions.md) · Next: [Events](events.md)
