<?php

namespace BlackParadise\LaravelAdmin\Http\Actions\Auth;

use BlackParadise\LaravelAdmin\Core\Services\AuthService;
use BlackParadise\LaravelAdmin\Http\Actions\Interfaces\Auth\LoginActionInterface;
use BlackParadise\LaravelAdmin\Http\Requests\Auth\LoginRequest;
use Exception;
use Illuminate\Http\RedirectResponse;

class LoginAction implements LoginActionInterface
{

    private AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * @param LoginRequest $request
     * @return RedirectResponse
     */
    public function __invoke(LoginRequest $request): RedirectResponse
    {
        try {
            $this->authService->login($request->validated());
        } catch (Exception $exception) {
            return redirect()->back()->withErrors([$exception->getMessage()]);
        }

        return redirect()->route('bpadmin.pages.index');
    }
}
