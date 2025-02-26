<?php

namespace BlackParadise\LaravelAdmin\Http\Actions\Entity\Interface;

use Illuminate\View\View;

interface EditEntityInterface
{
    public function __invoke(int $id): View;
}
