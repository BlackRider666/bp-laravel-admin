<?php

namespace BlackParadise\LaravelAdmin\Http\Actions\Interfaces\Entity;

use BlackParadise\LaravelAdmin\Http\Requests\Entity\StoreAbstractEntityRequest;
use Illuminate\Http\RedirectResponse;

interface StoreEntityInterface
{
    /**
     * @param StoreAbstractEntityRequest $request
     * @return RedirectResponse
     */
    public function __invoke(StoreAbstractEntityRequest $request): RedirectResponse;
}
