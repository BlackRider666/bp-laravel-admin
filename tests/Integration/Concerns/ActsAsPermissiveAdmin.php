<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Concerns;

use BlackParadise\CoreAdmin\Domain\Contracts\Auth\AuthorizationProviderContract;
use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\Contracts\ValueHasherContract;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Schema;

/** Task 2.1 helper: permissive authorization provider + an authenticated admin user. */
trait ActsAsPermissiveAdmin
{
    protected function permitEverything(): void
    {
        app()->bind(AuthorizationProviderContract::class, fn(): AuthorizationProviderContract => new class implements AuthorizationProviderContract {
            public function can(string $action, EntityDefinitionContract $entityDefinition): bool
            {
                return true;
            }
        });
    }

    /** Deterministic hasher: `secret` -> `stub_hashed_secret` (same convention as StubsValueHasher). */
    protected function stubHasher(): void
    {
        app()->bind(ValueHasherContract::class, fn(): ValueHasherContract => new class implements ValueHasherContract {
            public function hash(string $value): string
            {
                return 'stub_hashed_' . $value;
            }

            public function isHashed(string $value): bool
            {
                return str_starts_with($value, 'stub_hashed_') || str_starts_with($value, '$2y$');
            }
        });
    }

    protected function loginAdmin(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        $this->actingAs(User::forceCreate(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'x']));
    }
}
