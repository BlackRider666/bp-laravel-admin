<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Http\Presenters\Json;

use BlackParadise\LaravelAdmin\Http\Presenters\AuthPresenterInterface;
use Symfony\Component\HttpFoundation\Response;

final class JsonAuthPresenter implements AuthPresenterInterface
{
    public function showLoginForm(): Response
    {
        return response()->json(['page' => 'login']);
    }

    public function loginSuccess(): Response
    {
        return response()->json(['message' => 'Authenticated.']);
    }

    public function loginFailure(): Response
    {
        return response()->json(['message' => 'Invalid credentials.'], 401);
    }

    public function logoutSuccess(): Response
    {
        return response()->json(['message' => 'Logged out.']);
    }
}
