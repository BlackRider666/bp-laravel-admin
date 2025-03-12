<?php

namespace BlackParadise\LaravelAdmin\Http\Actions\Interfaces\Auth;

use Illuminate\Http\RedirectResponse;

interface LogoutActionInterface
{
    public function __invoke(): RedirectResponse;
}
