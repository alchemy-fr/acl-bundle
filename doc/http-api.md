# HTTP API

The bundle exposes a small JSON API to list, create, update and delete ACEs: front-end
applications can offer a "Share" dialog without any server-side code. Import its routes
first (see [Installation](installation.md#import-the-routes)); the examples below assume the
`/permissions` prefix.

- [Fields](#fields)
- [`GET /permissions/aces`](#get-permissionsaces)
- [`PUT /permissions/ace`](#put-permissionsace)
- [`DELETE /permissions/ace`](#delete-permissionsace)
- [Authorization](#authorization)
- [Errors](#errors)

## Fields

| Field        | Description                                                                                  |
|--------------|----------------------------------------------------------------------------------------------|
| `userType`   | `user` or `group`                                                                            |
| `userId`     | The user or group ID. Send `__ALL_USERS__` to target everybody (stored as `NULL`).           |
| `objectType` | One of the keys declared in `alchemy_acl.objects`                                            |
| `objectId`   | The object UUID. `null` or empty targets every object of the type.                           |
| `mask`       | The sum of the granted permission bits                                                       |
| `metadata`   | Free JSON object stored with the ACE (optional: when omitted, an existing ACE keeps its own) |
| `parentId`   | Optional discriminator, see [Concepts](concepts.md#several-entries-for-the-same-pair-parentid). Omitted means `NULL` |

Responses are serialized ACEs. When the ACE targets a specific user or group, the payload
also contains a `user` or `group` key filled by your repositories:

```json
{
    "id": "0b8e2c7a-3f1d-4e5b-9a6c-7d8e9f0a1b2c",
    "userType": "user",
    "userId": "u-42",
    "objectType": "publication",
    "objectId": "9f0c4d1e-7b2a-4c3e-8d5f-1a2b3c4d5e6f",
    "mask": 7,
    "parentId": null,
    "metadata": {"granted_by": "admin"},
    "user": {"id": "u-42", "username": "alice"}
}
```

## `GET /permissions/aces`

Lists ACEs. Filters are passed as query parameters: `userType`, `userId`, `objectType`,
`objectId`, `parentId`. An empty value or the string `null` matches `NULL` (for instance
`objectId=null` returns type-wide ACEs). Add `userIdWildcard=1` or `objectIdWildcard=1` to
also include the wildcard ACEs alongside the ones matching the given ID.

```bash
# ACEs of an object, including type-wide ones
curl "{HOST}/permissions/aces?objectType=publication&objectId=9f0c4d1e-7b2a-4c3e-8d5f-1a2b3c4d5e6f&objectIdWildcard=1"

# ACEs of a group
curl "{HOST}/permissions/aces?userType=group&userId=g-42"

# ACEs of a user on an object
curl "{HOST}/permissions/aces?userType=user&userId=u-42&objectType=publication&objectId=9f0c4d1e-7b2a-4c3e-8d5f-1a2b3c4d5e6f"
```

A user without `ROLE_ADMIN` must pass an `objectType` (and usually an `objectId`): the list is
restricted to the object, or to the type-wide ACEs, the authorization was checked on. Along
with an `objectId`, `objectIdWildcard=1` only adds the type-wide ACEs when `ACL_READ` is also
granted on the type (see [Authorization](#authorization)); otherwise it is ignored.

## `PUT /permissions/ace`

Creates or replaces an ACE. The body must be JSON (`Content-Type: application/json`): a
form-encoded body gets a `415`, since a cross-site form could send one without a CORS
preflight.

```bash
curl -X PUT "{HOST}/permissions/ace" \
    -H 'Content-Type: application/json' \
    -d '{
        "userType": "user",
        "userId": "u-42",
        "objectType": "publication",
        "objectId": "9f0c4d1e-7b2a-4c3e-8d5f-1a2b3c4d5e6f",
        "mask": 7,
        "metadata": {"granted_by": "admin"}
    }'
```

## `DELETE /permissions/ace`

Deletes the ACE identified by the body. Returns `true`, also when there was nothing to delete.

```bash
curl -X DELETE "{HOST}/permissions/ace" \
    -H 'Content-Type: application/json' \
    -d '{
        "userType": "user",
        "userId": "u-42",
        "objectType": "publication",
        "objectId": "9f0c4d1e-7b2a-4c3e-8d5f-1a2b3c4d5e6f"
    }'
```

## Authorization

- `ROLE_ADMIN` can do everything.
- Otherwise the application's voters are asked for `ACL_READ` (list) or `ACL_WRITE`
  (create, update, delete) on the object. The bundled `SetPermissionVoter` grants both to the
  owner of the object. Add your own voter to let other users share an object, for instance
  those holding the `SHARE` permission:

```php
use Alchemy\AclBundle\Model\AclUserInterface;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AclBundle\Security\PermissionManager;
use Alchemy\AclBundle\Security\Voter\SetPermissionVoter;
use App\Entity\Publication;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class SharePublicationVoter extends Voter
{
    public function __construct(private PermissionManager $permissionManager)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [SetPermissionVoter::ACL_READ, SetPermissionVoter::ACL_WRITE], true)
            && $subject instanceof Publication;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $user instanceof AclUserInterface
            && $this->permissionManager->isGranted($user, $subject, PermissionInterface::SHARE);
    }
}
```

When `objectId` is empty there is no object to vote on: the subject is an
`Alchemy\AclBundle\Security\ObjectTypeSubject` carrying the `objectType` and its class. The
bundle grants nothing on it by default, so write a voter if non-admins must manage type-wide
ACEs:

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

## Errors

| Status | When                                                                                   |
|--------|----------------------------------------------------------------------------------------|
| `400`  | Unknown `objectType`, `objectId` which is not a UUID, missing or unknown `userType`, missing `userId`, `mask` which is not a non-negative integer, `metadata` which is not an object, malformed JSON |
| `403`  | The voters denied the request                                                          |
| `415`  | The body of a `PUT` or `DELETE` is not JSON                                            |

Invalid requests get their `400` before any authorization check.

---

Previous: [Events](events.md) · Next: [Admin UI](admin-ui.md)
