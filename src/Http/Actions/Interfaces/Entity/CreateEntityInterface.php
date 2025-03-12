<?php

namespace BlackParadise\LaravelAdmin\Http\Actions\Interfaces\Entity;

use Inertia\Response;
use Illuminate\View\View;

interface CreateEntityInterface
{
    public function __invoke(): Response|View;
}
