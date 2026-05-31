<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Auth;

use BlackParadise\CoreAdmin\Domain\Contracts\Auth\AuthenticationProviderContract;

/**
 * Laravel implementation of {@see AuthenticationProviderContract}.
 *
 * Delegates all authentication operations to the Laravel Auth guard
 * configured via `bpadmin.guard` (default: 'web').
 */
final readonly class LaravelAuthProvider implements AuthenticationProviderContract
{
    public function attempt(array $credentials): bool
    {
        return auth()->guard($this->guardName())->attempt($credentials);
    }

    public function logout(): void
    {
        auth()->guard($this->guardName())->logout();
    }

    public function user(): ?object
    {
        return auth()->guard($this->guardName())->user();
    }

    /**
     * Check whether the current request is authenticated.
     * Convenience helper not required by the core contract.
     */
    public function isAuthenticated(): bool
    {
        return auth()->guard($this->guardName())->check();
    }

    /**
     * Return the configured guard name, falling back to 'web'.
     */
    private function guardName(): string
    {
        return config('bpadmin.guard', 'web');
    }
}
