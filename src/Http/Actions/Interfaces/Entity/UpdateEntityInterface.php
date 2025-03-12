<?php

namespace BlackParadise\LaravelAdmin\Http\Actions\Interfaces\Entity;

use BlackParadise\LaravelAdmin\Http\Requests\Entity\UpdateAbstractEntityRequest;
use Illuminate\Http\RedirectResponse;

interface UpdateEntityInterface
{
    public function __invoke(int $id, UpdateAbstractEntityRequest $request): RedirectResponse;
}
