# Custom Actions in BPAdmin

## Overview
BPAdmin provides a flexible system for handling CRUD operations using actions. Each action is implemented as a separate class and must implement the corresponding interface.

## Creating a Custom Action
To create a custom action, follow these steps:

1. **Define the Interface**
    - Each action (Index, Store, Update, Delete) has its own interface that defines the required method signature.
    - Example:
      ```php
      namespace BlackParadise\LaravelAdmin\Http\Actions\Entity\Interface;
 
      use Illuminate\Http\RedirectResponse;
      use BlackParadise\LaravelAdmin\Http\Requests\StoreAbstractEntityRequest;
 
      interface StoreEntityInterface
      {
          public function __invoke(StoreAbstractEntityRequest $request): RedirectResponse;
      }
      ```

2. **Implement the Action Class**
    - Your action class must implement the corresponding interface.
    - Example:
      ```php
      namespace BlackParadise\LaravelAdmin\Http\Actions\Entity;
 
      use BlackParadise\LaravelAdmin\Http\Actions\Entity\Interface\StoreEntityInterface;
      use BlackParadise\LaravelAdmin\Http\Requests\StoreAbstractEntityRequest;
      use Illuminate\Http\RedirectResponse;
 
      class StoreEntityAction implements StoreEntityInterface
      {
          public function __invoke(StoreAbstractEntityRequest $request): RedirectResponse
          {
              // Custom logic for storing entity
              return redirect()->route('bpadmin.entity.index'); // or your custom route
          }
      }
      ```

3. **Register the Action in Configuration**
   - Add the custom action class to the `bpadmin.php` config file under `custom_actions`:
     ```php
     return [
         'custom_actions' => [
             'entity_name' => [
                 'store' => \App\Actions\CustomStoreEntityAction::class,
                 'update' => \App\Actions\CustomUpdateEntityAction::class,
             ],
         ],
     ];
     ```

## Custom Validation in Store and Update Actions

For `Store` and `Update` actions, you must use either the provided request class (`StoreAbstractEntityRequest` or `UpdateAbstractEntityRequest`), the standard `Request` class.

Our request classes support many customizations, so this should not be an issue in most cases.

### Example Usage:

```php
public function __invoke(StoreAbstractEntityRequest $request): RedirectResponse
{
    // Your entity creation logic
}
```

By following these guidelines, you can easily create and customize actions while ensuring compatibility with BPAdmin.

