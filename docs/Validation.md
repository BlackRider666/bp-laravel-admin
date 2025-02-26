# Validation in BPAdmin

## Overview
BPAdmin provides a flexible validation system for entity actions, ensuring that data integrity is maintained. The validation process relies on request classes that either use predefined rules or dynamically generate rules based on the entity's structure.

## Validation Request Classes
BPAdmin provides base request classes that can be extended to define validation rules for different actions:

1. **`StoreAbstractEntityRequest`** - Used for validating `store` requests.
2. **`UpdateAbstractEntityRequest`** - Used for validating `update` requests.

### Customizing Validation Rules
Each entity class provides a `rules` array where you can define specific validation rules. If no custom rules are provided, the system generates them automatically based on the entity's attributes.

#### Example: Users
```php
class Users extends BPModel
{
    public string $model = \App\Models\User::class;

    public string $name = 'users';

    public array $fieldTypes = [
        'name' => [
            'type' => 'string',
            'required' => true,
        ],
        'email' => [
            'type' => 'string',
            'required' => true,
        ],
        'password' => [
            'type' => 'hashed',
            'required' => true,
        ],
    ];

    public array $searchFields = [
        'name'
    ];

    public array $tableHeaderFields = [
        'id','name'
    ];

    public array $showPageFields = [
        'id','name'
    ];
    
    public array $rules = [
        'store' => [],  // your rules for store here,
        'update' => [], // your rules for update here,
    ];
}
```

In this example:
- If custom validation rules exist in the model, they are used.
- If no custom rules exist, the system generates rules dynamically based on the entity model ```$fieldTypes```.

## Applying Validation in Actions
In store and update actions, validation is applied through request injection.

```php
public function __invoke(StoreAbstractEntityRequest $request): RedirectResponse
{
    // Handle entity creation
}
```

## Summary
- Use `StoreAbstractEntityRequest` for storing entities and `UpdateAbstractEntityRequest` for updating entities.
- Use your model fields if custom validation logic is needed.
- BPAdmin dynamically generates validation rules if none are explicitly defined.

This system ensures a structured and extendable approach to validation in BPAdmin.

