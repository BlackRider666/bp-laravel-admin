<?php

namespace BlackParadise\LaravelAdmin\Http\Actions\Auth;

use BlackParadise\LaravelAdmin\Core\Services\AuthService;
use BlackParadise\LaravelAdmin\Http\Actions\Interface\Auth\LogoutActionInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class LogoutAction implements LogoutActionInterface
{
    private AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * @return RedirectResponse
     */
    public function __invoke(): RedirectResponse
    {
        $this->authService->logout();

        return redirect()->route('bpadmin.pages.index');
    }
}
