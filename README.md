# Black-Paradise/Laravel-Admin

## Introduction

`Black-Paradise/Laravel-Admin` is a Laravel-based admin panel that is generated based on a configuration file. The configuration defines models and their respective fields, which are then used to create the admin panel structure.

## Installation

To install the package, run the following command:

```sh
composer require black-paradise/laravel-admin
```


Then, run the install command to generate the necessary files:

```sh
php artisan bpadmin:install
```

## Configuration

The package uses a configuration file located at `config/bpadmin.php`. This file contains:
- `title` – The name of the admin panel.
- `userEntity` – The user model used for authentication.
- `languages` – Supported languages.
- `entities` – List of models with settings for fields, pagination, validation, and display options.
- `menu` – The structure of the admin panel menu.

### Example Configuration

```php
return [
    'title' => 'BPAdmin',
    'userEntity' => \App\User::class,
    'languages' => ['en'],
    'entities' => [
        'users' => \App\User::class,
    ],
    'menu' => [
        'users' => [
            'icon' => 'mdi-account-group',
            'items' => [
                'users' => [
                    'icon' => 'mdi-account-group',
                ],
            ],
        ],
    ],
];
```

## Generated Classes

Each model defined in the configuration file gets a corresponding class generated in `App\BPAdmin`:

```php
<?php

namespace App\BPAdmin;

use BlackParadise\LaravelAdmin\Core\Models\BPModel;

class Users extends BPModel
{
    public string $model = \App\Models\User::class;

    public string $filePath = 'users';

    public string $name = 'users';

    protected array $fieldTypes = [
        'first_name' => [
            'type' => 'string',
            'required' => true,
        ],
        'second_name' => [
            'type' => 'string',
            'required' => true,
        ],
        'surname' => [
            'type' => 'string',
            'required' => true,
        ],
        'avatar' => [
            'type' => 'file',
            'required' => false,
        ],
        'email' => [
            'type' => 'email',
            'required' => true,
        ],
        'password' => [
            'type' => 'hashed',
            'required' => true,
        ],
        'phone' => [
            'type' => 'string',
            'required' => false,
        ],
        'city_id' => [
            'type' => 'BelongsTo',
            'method' => 'city',
            'required' => true,
        ],
        'verify' => [
            'type' => 'boolean',
            'required' => true,
        ],
        'roles_method' => [
            'type' => 'BelongsToMany',
            'method' => 'roles',
            'multiple' => true,
        ],
        'permissions_method' => [
            'type' => 'BelongsToMany',
            'method' => 'permissions',
            'multiple' => true,
        ],
    ];

    public array $searchFields = [
        'first_name',
        'second_name',
        'surname',
        'email'
    ];

    public array $tableHeaderFields = [
        'first_name',
        'second_name',
        'surname',
        'email'
    ];

    public array $showPageFields = [
        'id',
        'first_name',
        'email',
        'role_id',
    ];
}
```

## Inputs

The package includes various input types for form fields in the admin panel. These inputs determine how data is displayed and edited.

### Available Input Types

- `BelongsTo` – Handles `BelongsTo` relationships.
- `BelongsToMany` – Handles `BelongsToMany` relationships.
- `boolean` – Checkbox for boolean values.
- `editor` – Rich text editor input.
- `email` – Input field for email addresses.
- `file` – File upload field.
- `float` – Input field for floating-point numbers.
- `hidden` – Hidden input field.
- `integer` – Input field for integer numbers.
- `hashed` – Password input field.
- `string` – Standard text input field.
- `submit` – Submit button for forms.
- `text` – Multi-line text area.
- `translatableEditor` – Rich text editor with translation support.
- `translatable` – Text input with translation support.

## Console Commands

### `bpadmin:translation-generate`

#### Description
Generates translation files for all entities defined in `bpadmin.entities`.

#### Usage
```sh
php artisan bpadmin:translation-generate
```

#### How It Works
- Creates translation files in `resource/lang/vendor/bpadmin/en/`.
- Extracts fillable fields from the model using `TypeFromTable`.
- Generates a PHP array with translations where field names are capitalized.
- Saves the translations in `{model}.php` files.

#### Example Output File (`resource/lang/vendor/bpadmin/en/users.php`)

```php
<?php
return [
    'name' => 'Name',
    'email' => 'Email',
    'role_id' => 'Role ID',
    'actions' => 'Actions',
];
```

## Roadmap
- Support for custom components.
- Advanced validation handling.
- Additional customization options for pages.

## License
This package is open-source and licensed under the MIT license.

## Support

If you would like to support this package, you can do so on [Patreon](https://patreon.com/BlackParadise?utm_medium=unknown&utm_source=join_link&utm_campaign=creatorshare_creator&utm_content=copyLink).
