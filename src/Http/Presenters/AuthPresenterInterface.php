<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Http\Presenters;

use Symfony\Component\HttpFoundation\Response;

interface AuthPresenterInterface
{
    public function showLoginForm(): Response;

    public function loginSuccess(): Response;

    public function loginFailure(): Response;

    public function logoutSuccess(): Response;
}
