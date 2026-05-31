<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Http\Controllers;

use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Thin base controller for all BPAdmin admin controllers.
 *
 * This class intentionally contains NO business logic.
 * It provides shared infrastructure helpers only: access to the entity
 * registry and a convenience JSON response wrapper.
 *
 * Concrete controllers must extend this class and inject their own
 * use cases for actual business operations.
 */
abstract class AbstractAdminController extends Controller
{
    public function __construct(
        protected readonly EntityDefinitionRegistry $registry,
    ) {}

    /**
     * Extract the entity name from the current route.
     * Relies on the {entity} route parameter registered by BPAdminRouteRegistrar.
     */
    protected function entityName(Request $request): string
    {
        return (string) $request->route('entity');
    }

}
