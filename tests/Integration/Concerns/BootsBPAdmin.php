<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Schema;

/**
 * Minimal bootstrap for SafeFileDownload and similar controller tests
 * that do not need entity fixtures but do need an authenticated user.
 *
 * Provides:
 *   - A `users` table migration (in-memory SQLite)
 *   - An `actingAsAdmin()` helper that creates + authenticates a User
 *     and returns $this for method chaining
 */
trait BootsBPAdmin
{
    protected function setUpBPAdmin(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Create a minimal admin User, authenticate the request, and return $this
     * so the caller can chain HTTP assertion calls:
     *
     *   $this->actingAsAdmin()->get('/admin/...')->assertOk();
     */
    protected function actingAsAdmin(): static
    {
        $user = new User();
        $user->name = 'Admin';
        $user->email = 'admin@example.com';
        $user->password = bcrypt('password');
        $user->save();

        $this->actingAs($user);

        return $this;
    }
}
