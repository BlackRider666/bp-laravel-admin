<?php

namespace BlackParadise\LaravelAdmin\Http\Actions\Interfaces\Auth;

use BlackParadise\LaravelAdmin\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;

interface LoginActionInterface
{
    public function __invoke(LoginRequest $request): RedirectResponse;
}
