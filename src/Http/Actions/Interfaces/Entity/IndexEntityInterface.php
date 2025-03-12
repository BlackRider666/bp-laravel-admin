<?php

namespace BlackParadise\LaravelAdmin\Http\Actions\Interfaces\Entity;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Inertia\Response;

interface IndexEntityInterface
{
    public function __invoke(Request|FormRequest $request): Response|View;
}
