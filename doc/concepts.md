# Concepts

- [Access control entries](#access-control-entries)
- [Permissions](#permissions)
- [Wildcards](#wildcards)
- [How a check is resolved](#how-a-check-is-resolved)
- [Several entries for the same pair: `parentId`](#several-entries-for-the-same-pair-parentid)

## Access control entries

Every permission is stored as an **access control entry** (ACE), a row of a single Doctrine
table:

| Column       | Description                                                                     |
|--------------|---------------------------------------------------------------------------------|
| `userType`   | `user` (`0`) or `group` (`1`)                                                   |
| `userId`     | The user or group ID. `NULL` means *all users*                                  |
| `objectType` | An object type declared in `alchemy_acl.objects`                                |
| `objectId`   | The object UUID. `NULL` means *every object of the type*                        |
| `mask`       | The granted permissions, as a bitmask                                           |
| `metadata`   | A free JSON object (who granted it, why, until when…)                           |
| `parentId`   | An optional discriminator, see [below](#several-entries-for-the-same-pair-parentid) |
| `createdAt`  | The creation date                                                               |

The user types are constants of `Alchemy\AclBundle\Model\AccessControlEntryInterface`:
`TYPE_USER_VALUE` and `TYPE_GROUP_VALUE`.

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

`PermissionInterface::PERMISSIONS` maps each name to its value.

Permissions are independent: `EDIT` does not imply `VIEW`. A mask of `7` grants `VIEW`,
`CREATE` and `EDIT`. The bundle gives no meaning to any of them: your application decides
what `OPERATOR` or `SHARE` allow. The `CHILD_*` permissions are meant for applications that
express rights on the children of an object (the assets of a collection, for instance).

```php
use Alchemy\AclBundle\Security\PermissionInterface;

$mask = PermissionInterface::VIEW | PermissionInterface::EDIT; // 5
```

## Wildcards

- **All users**: an ACE with a `NULL` `userId` applies to everybody. Through the API and the
  admin UI, send `AccessControlEntryInterface::USER_WILDCARD` (`__ALL_USERS__`) as the
  `userId`.
- **All objects of a type**: an ACE with a `NULL` `objectId` applies to every object of its
  `objectType`.

Both can be combined: *everybody can view every publication* is a single ACE.

## How a check is resolved

For a user, an object and a permission, access is granted if any of the following holds:

1. the user is the owner of the object (`getAclOwnerId()`);
2. an ACE carrying the permission targets the user, one of the user's groups, or all users,
   on this object or on the whole object type.

There is no deny rule: entries only add permissions. To take a right away, update or delete
the entry that grants it.

## Several entries for the same pair: `parentId`

An optional `parentId` lets you keep several ACEs for the same user and object, one per
*source*. For instance, when an asset is shared through two collections, each collection
can hold its own ACE on the asset, and removing the asset from one collection only deletes
the ACE of that collection.

The unique constraint is on `(userType, userId, objectType, objectId, parentId)`. A
`parentId` is a string of at most 39 characters (enough for a prefix and a UUID, such as
`c:9f0c4d1e-7b2a-4c3e-8d5f-1a2b3c4d5e6f`).

---

Previous: [Installation](installation.md) · Next: [Checking permissions](checking-permissions.md)
