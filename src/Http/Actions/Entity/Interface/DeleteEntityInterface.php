<?php

namespace BlackParadise\LaravelAdmin\Http\Actions\Entity\Interface;

use Illuminate\Http\RedirectResponse;

interface DeleteEntityInterface
{
    public function __invoke(int $id): RedirectResponse;
}
