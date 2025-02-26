<?php

namespace BlackParadise\LaravelAdmin\Http\Actions\Entity\Interface;

use Illuminate\View\View;

interface CreateEntityInterface
{
    public function __invoke(): View;
}
