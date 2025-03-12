<?php

namespace BlackParadise\LaravelAdmin\Http\Actions\Interfaces\Entity;

use Illuminate\Http\RedirectResponse;

interface DeleteEntityInterface
{
    public function __invoke(int $id): RedirectResponse;
}
