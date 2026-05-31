<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Http;

use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

/**
 * Integration tests for AdminAuthController.
 *
 * Verifies login form display, successful login, failed login, and logout
 * through the real HTTP stack with JsonAuthPresenter responses.
 */
final class AdminAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    // ------------------------------------------------------------------
    // showLoginForm — GET /admin/auth/login
    // ------------------------------------------------------------------

    public function test_show_login_form_returns_200_with_page_key(): void
    {
        $response = $this->getJson('/admin/auth/login');

        $response->assertOk()
            ->assertJson(['page' => 'login']);
    }

    // ------------------------------------------------------------------
    // login — POST /admin/auth/login
    // ------------------------------------------------------------------

    public function test_login_with_valid_credentials_returns_authenticated_message(): void
    {
        $user = new User();
        $user->name = 'Admin';
        $user->email = 'admin@example.com';
        $user->password = bcrypt('secret');
        $user->save();

        $response = $this->postJson('/admin/auth/login', [
            'email'    => 'admin@example.com',
            'password' => 'secret',
        ]);

        $response->assertOk()
            ->assertJson(['message' => 'Authenticated.']);
    }

    public function test_login_with_invalid_credentials_returns_401(): void
    {
        $user = new User();
        $user->name = 'Admin';
        $user->email = 'admin@example.com';
        $user->password = bcrypt('correct-password');
        $user->save();

        $response = $this->postJson('/admin/auth/login', [
            'email'    => 'admin@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Invalid credentials.']);
    }

    public function test_login_with_missing_email_returns_422(): void
    {
        $response = $this->postJson('/admin/auth/login', [
            'password' => 'secret',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_with_missing_password_returns_422(): void
    {
        $response = $this->postJson('/admin/auth/login', [
            'email' => 'admin@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_login_with_invalid_email_format_returns_422(): void
    {
        $response = $this->postJson('/admin/auth/login', [
            'email'    => 'not-an-email',
            'password' => 'secret',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    // ------------------------------------------------------------------
    // logout — POST /admin/auth/logout
    // ------------------------------------------------------------------

    public function test_logout_returns_logged_out_message(): void
    {
        $user = new User();
        $user->name = 'Admin';
        $user->email = 'admin@example.com';
        $user->password = bcrypt('secret');
        $user->save();

        $this->actingAs($user);

        $response = $this->postJson('/admin/auth/logout');

        $response->assertOk()
            ->assertJson(['message' => 'Logged out.']);
    }
}
