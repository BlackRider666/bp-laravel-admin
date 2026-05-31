<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Auth;

use BlackParadise\CoreAdmin\Domain\Contracts\Auth\AuthenticationProviderContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Auth\AuthorizationProviderContract;
use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use Illuminate\Contracts\Auth\Access\Gate;

/**
 * Laravel implementation of {@see AuthorizationProviderContract}.
 *
 * Delegates authorization checks to the Laravel Gate, scoped to the
 * currently authenticated user retrieved via {@see AuthenticationProviderContract}.
 *
 * Gate ability names are derived as "{action}.{entityName}", e.g. "view.users".
 * When no user is authenticated, all checks return false without hitting the Gate.
 */
final readonly class LaravelAuthorizationProvider implements AuthorizationProviderContract
{
    public function __construct(
        private Gate $gate,
        private AuthenticationProviderContract $auth,
    ) {}

    /**
     * {@inheritDoc}
     *
     * Returns false immediately when no user is authenticated.
     * Otherwise delegates to Gate::forUser()->check() with the ability
     * "{action}.{entityName}" and the entity definition as payload.
     */
    public function can(string $action, EntityDefinitionContract $entityDefinition): bool
    {
        $user = $this->auth->user();

        if ($user === null) {
            return false;
        }

        $ability = $action . '.' . $entityDefinition->name();

        return $this->gate->forUser($user)->check($ability, $entityDefinition);
    }
}
