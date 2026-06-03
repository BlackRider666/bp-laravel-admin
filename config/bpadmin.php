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

    /*
    |--------------------------------------------------------------------------
    | Sidebar Menu
    |--------------------------------------------------------------------------
    |
    | Defines the admin sidebar. When set, the sidebar is driven entirely by
    | this ordered list of groups: only the listed entities are rendered, in
    | exactly this order and grouping (no auto-discovery). Each group is an
    | array ['label' => ..., 'icon' => ..., 'entities' => [...]] where each
    | entity is the snake_case name of a registered EntityDefinition.
    |
    | An 'entities' item is either a bare name (inherits the group icon, label
    | from the entity definition) or an array ['entity' => ..., 'icon' => ...,
    | 'label' => ...] to override that item's icon and/or label:
    |
    |   'entities' => ['books', ['entity' => 'authors', 'icon' => 'user', 'label' => 'Writers']],
    |
    | Group and item labels are localized: a literal string or a translation
    | key both work (e.g. 'label' => 'bpadmin::ui.catalog' or 'menu.catalog').
    |
    | When this key is omitted/empty, the sidebar falls back to listing every
    | auto-discovered entity, ungrouped. Add your own groups/entities here:
    |
    |   ['label' => 'Catalog', 'icon' => 'book', 'entities' => ['books', 'authors']],
    |
    | Bundled icon names (BladeUI IconSet): dashboard, list, grid, users, user,
    | book, document, newspaper, folder, archive, tag, comment, calendar,
    | building, map, globe, image, star, chart, cart, mail, bell, lock, shield,
    | key, cog, link. Add your own (or override) via the 'icons' key below.
    |
    */
    'menu' => [
        ['label' => 'Users', 'icon' => 'users', 'entities' => ['users']],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Icons
    |--------------------------------------------------------------------------
    |
    | Extend or override the bundled icon set without touching the package.
    | Map an icon name to its inner SVG markup (24x24 viewBox, stroke-based,
    | no fill) — or to a complete <svg> string, which is rendered verbatim:
    |
    |   'icons' => [
    |       'rocket' => '<path stroke-linecap="round" stroke-width="2" d="M5 5l14 14"/>',
    |   ],
    |
    */
    'icons' => [],

];
