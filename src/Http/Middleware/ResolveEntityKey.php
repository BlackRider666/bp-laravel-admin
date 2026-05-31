<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Http\Middleware;

use BlackParadise\CoreAdmin\Domain\ValueObjects\EntityKey;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the {id} route parameter into an EntityKey domain object and
 * stores it in the request attributes bag.
 *
 * Controllers retrieve the resolved key via:
 *   $key = $request->attributes->get('entity_key');
 *
 * Runs after ValidateEntity so the entity definition is guaranteed to exist.
 * Routes without an {id} segment are passed through unchanged.
 */
final readonly class ResolveEntityKey
{
    public function __construct(
        private EntityDefinitionRegistry $registry,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->route('id');

        if ($id !== null) {
            $entityName = (string) $request->route('entity');

            if (!$this->registry->has($entityName)) {
                abort(404);
            }

            $definition = $this->registry->get($entityName);
            $value = $definition->keyType() === 'int' ? (int) $id : (string) $id;
            $request->attributes->set('entity_key', new EntityKey($value, $definition->keyType()));
        }

        return $next($request);
    }
}
