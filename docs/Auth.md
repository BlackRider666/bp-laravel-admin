# Authentication and Authorization

## Authentication

BPAdmin provides a flexible authentication system that allows customization of user authentication and login actions.

### Configuration
Authentication settings can be customized in the `bpadmin.php` config file under the `auth` section:

```php
'auth' => [
    'userEntity'    => \App\User::class, // The user model
    'username'      => 'email', // The field used for login
    'custom_actions' => [
        // 'loginPage' => \App\BPAdmin\Action\Auth\YourCustomAction::class,
        // 'login' => \App\BPAdmin\Action\Auth\YourCustomAction::class,
        // 'logout' => \App\BPAdmin\Action\Auth\YourCustomAction::class,
    ],
    'auth_rules'     => static function (array $credentials, \Illuminate\Contracts\Auth\Authenticatable $user) : bool {
        // Custom authentication rules
        return true;
    }
],
```

### Custom Authentication Actions
BPAdmin allows you to override default authentication actions by specifying your own custom classes in the `custom_actions` array. You can replace the following actions:

- **Login Page (`loginPage`)**
- **Login (`login`)**
- **Logout (`logout`)**

Example:
```php
'custom_actions' => [
    'loginPage' => \App\BPAdmin\Action\Auth\CustomLoginPageAction::class,
    'login' => \App\BPAdmin\Action\Auth\CustomLoginAction::class,
    'logout' => \App\BPAdmin\Action\Auth\CustomLogoutAction::class,
],
```

These actions must implement their respective interfaces (`LoginPageActionInterface`, `LoginActionInterface`, `LogoutActionInterface`).

### Custom Authentication Rules
If you need additional authentication checks beyond password validation, you can define custom authentication rules using the `auth_rules` closure. This function receives the user credentials and the authenticated user model:

```php
'auth_rules' => static function (array $credentials, \Illuminate\Contracts\Auth\Authenticatable $user) : bool {
    return $user->hasRole('admin');
},
```

These rules will be applied after Laravel's standard authentication process.

## Authorization
BPAdmin follows Laravel's authorization best practices by using **policies and gates**. Each entity can have a registered policy, and BPAdmin will automatically check if the user has permissions before displaying or executing actions.

### Using Policies
If a policy is registered for an entity, BPAdmin will check user permissions before allowing access to routes and actions.

Example policy:
```php
class EntityPolicy {
    public function view(User $user) {
        return $user->hasRole('admin');
    }

    public function update(User $user) {
        return $user->hasPermission('edit-entity');
    }
}
```

If no policy exists for an entity, full access is granted by default.

### Automatic Route and Menu Filtering
BPAdmin dynamically filters routes and menu items based on user permissions. If a user lacks permissions for `show`, `edit`, or `delete`, no action buttons will be displayed in the table. Similarly, menu items corresponding to entities the user cannot access will be hidden.

By following these guidelines, BPAdmin provides a secure and scalable authentication and authorization system that integrates seamlessly with Laravel.

