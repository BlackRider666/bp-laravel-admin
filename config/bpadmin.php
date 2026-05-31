<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Entities
    |--------------------------------------------------------------------------
    |
    | List your Eloquent model classes here. Run `php artisan bpadmin:install`
    | to scaffold App\BPAdmin\{Name} EntityDefinition classes from these models.
    | BPAdmin auto-discovers all EntityDefinition classes in app/BPAdmin/ at runtime.
    |
    */
    'entities' => [
        // \App\Models\User::class,
        // \App\Models\Product::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Entity Discovery Paths
    |--------------------------------------------------------------------------
    |
    | Directories where BPAdmin scans for EntityDefinition subclasses.
    | All classes extending EntityDefinition in these paths will be
    | auto-registered at boot time.
    |
    */
    'discovery_paths' => [
        // app_path('BPAdmin'),
        // app_path('Modules/Shop/BPAdmin'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Prefix & Middleware
    |--------------------------------------------------------------------------
    |
    | The URL prefix under which all BPAdmin routes are registered.
    | Add any global middleware that should wrap every admin route.
    |
    */
    'prefix' => env('BPADMIN_PREFIX', 'admin'),
    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    |
    | The Laravel guard used for admin authentication checks.
    |
    */
    'guard' => env('BPADMIN_GUARD', 'web'),

    /*
    |--------------------------------------------------------------------------
    | File Storage
    |--------------------------------------------------------------------------
    |
    | The Laravel filesystem disk used for file/image uploads.
    |
    */
    'storage_disk' => env('BPADMIN_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    |
    | Default number of records per page when not overridden by the
    | EntityDefinition's defaultPerPage() method.
    |
    */
    'per_page' => 15,

    /*
    |--------------------------------------------------------------------------
    | Locales
    |--------------------------------------------------------------------------
    |
    | Explicit list of locales offered by the admin panel. When null,
    | locales are auto-discovered by scanning lang/vendor/bpadmin/ (plus
    | the bundled 'en' baseline). Set this to lock the visible locale menu.
    |
    */
    'locales' => null,

];
