<?php

namespace BlackParadise\LaravelAdmin\Http\Actions\Interfaces\Entity;

use Illuminate\View\View;
use Inertia\Response;

interface ShowEntityInterface
{
    public function __invoke(int $id): Response|View;
}
