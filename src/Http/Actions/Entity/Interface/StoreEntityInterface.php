<?php

namespace BlackParadise\LaravelAdmin\Http\Actions\Entity\Interface;

use BlackParadise\LaravelAdmin\Http\Requests\StoreAbstractEntityRequest;
use Illuminate\Http\RedirectResponse;

interface StoreEntityInterface
{
    /**
     * @param StoreAbstractEntityRequest $request
     * @return RedirectResponse
     */
    public function __invoke(StoreAbstractEntityRequest $request): RedirectResponse;
}
