# BPAdmin — Laravel Adapter (v3)

Laravel 11/12/13 adapter for BPAdmin v3. Provides Eloquent repository/mutator, CRUD controllers, service provider, and route wiring. Requires `bp-admin-core` (pure PHP domain layer).

## Installation

```bash
composer require black-paradise/laravel-admin
```

Publish the config:

```bash
php artisan vendor:publish --tag=bpadmin-config
```

Scaffold entity definition stubs:

```bash
php artisan bpadmin:install
```

## Configuration

All options live in `config/bpadmin.php`. Key keys:

| Key | Default | Description |
|-----|---------|-------------|
| `prefix` | `'admin'` | URL prefix for all admin routes |
| `middleware` | `['web']` | Middleware applied to all admin routes |
| `guard` | `'web'` | Laravel auth guard |
| `storage_disk` | `'public'` | Filesystem disk for file uploads |
| `per_page` | `15` | Default pagination size |

## Middleware & CSRF

BPAdmin routes use `config('bpadmin.middleware', ['web'])`. The default `web`
middleware group includes `VerifyCsrfToken`. **If you override
`bpadmin.middleware`, preserve `VerifyCsrfToken`** — using only `api` will
silently strip CSRF protection from every admin form (login, create, update,
delete).

To add custom middleware while keeping CSRF, append rather than replace:
`config(['bpadmin.middleware' => ['web', 'auth.custom']])`.

## Entity Definitions

Create `App\BPAdmin\{Name}` classes extending `EntityDefinition`:

```php
use BlackParadise\CoreAdmin\EntityDefinition;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\CoreAdmin\Domain\Fields\BooleanField;

class Users extends EntityDefinition
{
    public string $model = \App\Models\User::class;

    public function fields(): array
    {
        return [
            TextField::make('name')->required()->searchable(),
            TextField::make('email')->required(),
            BooleanField::make('is_active'),
        ];
    }
}
```

`DashboardServiceProvider` auto-discovers all `EntityDefinition` subclasses in `app/BPAdmin/` at boot.

## Production Deployment

During a production deploy, cache entity definitions alongside Laravel's own caches:

```bash
php artisan config:cache
php artisan route:cache
php artisan bpadmin:cache   # pre-builds entity manifest → bootstrap/cache/bpadmin-entities.php
```

Without `bpadmin:cache`, every request walks the filesystem to discover entity classes.
This is fine locally, but in production it adds measurable overhead and triggers a boot-time
log warning to remind you to run the cache command.

To clear the entity manifest (e.g. during rollback or after removing an entity):

```bash
php artisan bpadmin:cache --clear
```

## License

MIT
