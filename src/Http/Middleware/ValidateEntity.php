<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Http\Middleware;

use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that validates the {entity} route parameter against the
 * EntityDefinitionRegistry.
 *
 * A 404 is returned when the entity name is missing or not registered.
 * This ensures entity routes never reach controllers with an unknown entity.
 */
final readonly class ValidateEntity
{
    public function __construct(
        private EntityDefinitionRegistry $registry,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $entity = $request->route('entity');

        if ($entity === null || !$this->registry->has((string) $entity)) {
            // Do not echo the user-supplied entity name back in the response
            // body — it would render unfiltered input in error pages.
            abort(404);
        }

        return $next($request);
    }
}
