<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Http\Controllers;

use BlackParadise\CoreAdmin\Application\UseCases\Auth\LoginUseCase;
use BlackParadise\CoreAdmin\Application\UseCases\Auth\LogoutUseCase;
use BlackParadise\LaravelAdmin\Http\Presenters\AuthPresenterInterface;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

/**
 * Thin authentication controller.
 *
 * Delegates business logic to LoginUseCase / LogoutUseCase and
 * response formatting to {@see AuthPresenterInterface}.
 *
 * Handles HTTP-level session security concerns: session regeneration
 * on successful login (prevents session fixation) and full invalidation
 * on logout. Throttling (5/min) is applied via route middleware.
 */
final class AdminAuthController extends Controller
{
    public function __construct(
        private readonly LoginUseCase $loginUseCase,
        private readonly LogoutUseCase $logoutUseCase,
        private readonly AuthPresenterInterface $presenter,
    ) {}

    public function showLoginForm(): Response
    {
        return $this->presenter->showLoginForm();
    }

    public function login(Request $request): Response
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $success = $this->loginUseCase->execute($credentials);

        if (!$success) {
            return $this->presenter->loginFailure();
        }

        // Regenerate session ID to prevent session fixation attacks.
        $request->session()->regenerate();

        return $this->presenter->loginSuccess();
    }

    public function logout(Request $request): Response
    {
        $this->logoutUseCase->execute();

        // Invalidate the session and rotate CSRF token so post-logout
        // requests cannot reuse the previous session cookie.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->presenter->logoutSuccess();
    }
}
