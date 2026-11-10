# Admin UI (EasyAdmin) and form types

- [Permission pages](#permission-pages)
- [Choosing the permissions shown](#choosing-the-permissions-shown)
- [Form types](#form-types)

## Permission pages

The bundle ships an [EasyAdmin 5](https://github.com/EasyCorp/EasyAdminBundle) page to manage
permissions with a checkbox matrix, either on one object or on every object of a type. It
relies on EasyAdmin's own components and widgets only: no extra asset to install.

- add a user, a group or *All users* from a searchable list;
- tick the permissions of each of them, or *All* at once; every change is saved right away
  through the [HTTP API](http-api.md);
- on an object, the type-wide ACEs are listed too, read-only, so you see everything that
  applies to it;
- ACEs carrying a `parentId` are labelled with it and stay editable.

The page calls the API through the admin routes, which you import next to your admin area:

```yaml
# config/routes/alchemy_acl_admin.yaml
alchemy_acl_admin:
    resource: '@AlchemyAclBundle/config/routing/permissions_admin.yaml'
    prefix: /admin/permissions
```

Render the `@AlchemyAcl/easyadmin/permissions.html.twig` template from your own admin
controllers, with the parameters built by `PermissionView::getViewParameters()` (pass `null`
as the ID for the type-wide page) plus two optional URLs:

| Parameter                | Description                                                        |
|--------------------------|--------------------------------------------------------------------|
| `back_url`               | Shows a "Back" button                                              |
| `global_permissions_url` | Object page only: a button to the type-wide page of the same type |

Add the actions to your dashboard (or to any admin controller) with `#[AdminRoute]`:

```php
use Alchemy\AclBundle\Admin\PermissionView;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Component\HttpFoundation\Response;

#[AdminRoute('/acl/{type}', name: 'acl_global_permissions')]
public function globalPermissions(PermissionView $view, string $type): Response
{
    return $this->render('@AlchemyAcl/easyadmin/permissions.html.twig', $view->getViewParameters($type, null));
}

#[AdminRoute('/acl/{type}/{id}', name: 'acl_object_permissions')]
public function objectPermissions(PermissionView $view, string $type, string $id): Response
{
    return $this->render('@AlchemyAcl/easyadmin/permissions.html.twig', [
        'back_url' => $this->generateUrl('admin'),
        'global_permissions_url' => $this->generateUrl('admin_acl_global_permissions', ['type' => $type]),
    ] + $view->getViewParameters($type, $id));
}
```

EasyAdmin prefixes the route names with the one of the dashboard (`admin` here).

When the object implements `Stringable`, its string representation is used as the page
title. The users and groups offered come from your
[repositories](installation.md#connect-your-users-and-groups).

The page sends its requests as the logged-in admin, so the [API authorization
rules](http-api.md#authorization) apply.

## Choosing the permissions shown

The columns of the matrix are the permissions listed in `alchemy_acl.enabled_permissions`:

```yaml
# config/packages/alchemy_acl.yaml
alchemy_acl:
    enabled_permissions: [VIEW, EDIT, DELETE, SHARE]
```

The default is `[VIEW, CREATE, EDIT, DELETE, OPERATOR, OWNER]`; an empty list shows all of
them. Unknown names are rejected when the container is built.

A checkbox only sets or clears its own bit: the permissions that are not shown, such as a
`CHILD_VIEW` granted from PHP, are kept when an admin edits the row.

## Form types

With `symfony/form` installed, three form types help build your own screens:

| Type                 | Choices                                                         |
|----------------------|-----------------------------------------------------------------|
| `ObjectTypeFormType` | The object types declared in `alchemy_acl.objects`              |
| `PermissionFormType` | Every permission, as expanded checkboxes (multiple choice)      |
| `UserTypeFormType`   | `user` or `group`                                               |

They live in the `Alchemy\AclBundle\Form` namespace and extend `ChoiceType`, so all its
options apply.

---

Previous: [HTTP API](http-api.md) · Next: [Contributing](contributing.md)
