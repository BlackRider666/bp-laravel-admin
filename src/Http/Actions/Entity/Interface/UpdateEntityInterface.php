<?php

namespace BlackParadise\LaravelAdmin\Http\Actions\Entity\Interface;

use BlackParadise\LaravelAdmin\Http\Requests\UpdateAbstractEntityRequest;
use Illuminate\Http\RedirectResponse;

interface UpdateEntityInterface
{
    public function __invoke(int $id, UpdateAbstractEntityRequest $request): RedirectResponse;
}
