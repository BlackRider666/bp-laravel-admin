<?php

namespace BlackParadise\LaravelAdmin\Http\Actions\Entity;

use BlackParadise\LaravelAdmin\Core\Models\BPModel;
use BlackParadise\LaravelAdmin\Http\Actions\Interfaces\Entity\StoreEntityInterface;
use BlackParadise\LaravelAdmin\Http\Actions\Traits\HandlesEntityAuthorization;
use BlackParadise\LaravelAdmin\Http\Requests\Entity\StoreAbstractEntityRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;

class StoreEntityAction implements StoreEntityInterface
{
    use HandlesEntityAuthorization;

    private BPModel $BPModel;

    public function __construct(BPModel $BPModel)
    {
        $this->BPModel = $BPModel;
    }

    /**
     * @param StoreAbstractEntityRequest $request
     * @return RedirectResponse
     * @throws AuthorizationException
     */
    public function __invoke(StoreAbstractEntityRequest $request): RedirectResponse
    {
        $this->authorizeAction('create');

        $this->BPModel->storeEntity($request->validated());

        return redirectToIndexModelPage($this->BPModel->name);
    }
}
