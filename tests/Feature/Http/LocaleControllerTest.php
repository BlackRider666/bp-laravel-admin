<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Feature\Http;

use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

final class LocaleControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(EncryptCookies::class);

        // Locale switching now requires an authenticated admin (see M4 in the
        // v3 hardening pass). Provide a minimal users table + acting-as user.
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        $user = new User();
        $user->name = 'Admin';
        $user->email = 'admin@example.com';
        $user->password = bcrypt('password');
        $user->save();

        $this->actingAs($user);
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('bpadmin.locales', ['en', 'uk']);
    }

    public function test_switch_stores_cookie_and_redirects_back(): void
    {
        $response = $this->from('/admin/users')
            ->post('/admin/locale', ['locale' => 'uk']);

        $response->assertRedirect('/admin/users');

        $cookie = collect($response->headers->getCookies())
            ->first(fn($c): bool => $c->getName() === 'bpadmin_locale');

        self::assertNotNull($cookie);
        self::assertSame('uk', $cookie->getValue());
    }

    public function test_switch_rejects_unknown_locale(): void
    {
        $response = $this->from('/admin/users')
            ->postJson('/admin/locale', ['locale' => 'zz']);

        $response->assertStatus(422);
    }

    public function test_switch_rejects_missing_locale(): void
    {
        $response = $this->from('/admin/users')
            ->postJson('/admin/locale', []);

        $response->assertStatus(422);
    }

    public function test_unauthenticated_switch_is_rejected(): void
    {
        auth()->logout();

        $response = $this->postJson('/admin/locale', ['locale' => 'uk']);

        $response->assertStatus(401);
    }
}
