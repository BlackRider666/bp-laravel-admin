<?php

namespace BlackParadise\LaravelAdmin\Http\Actions\Interfaces\Auth;

use Illuminate\View\View;
use Inertia\Response;

interface LoginPageActionInterface
{
    public function __invoke(): Response|View;
}
