<?php

namespace BlackParadise\LaravelAdmin\Http\Actions\Entity;

use BlackParadise\LaravelAdmin\Core\Models\BPModel;
use BlackParadise\LaravelAdmin\Http\Actions\Entity\Interface\StoreEntityInterface;
use BlackParadise\LaravelAdmin\Http\Requests\StoreAbstractEntityRequest;
use Illuminate\Http\RedirectResponse;

class StoreEntityAction implements StoreEntityInterface
{
    private BPModel $BPModel;

    public function __construct(BPModel $BPModel)
    {
        $this->BPModel = $BPModel;
    }

    /**
     * @param StoreAbstractEntityRequest $request
     * @return RedirectResponse
     */
    public function __invoke(StoreAbstractEntityRequest $request): RedirectResponse
    {
        $this->BPModel->storeEntity($request->validated());

        return redirectToIndexModelPage($this->BPModel->name);
    }
}
