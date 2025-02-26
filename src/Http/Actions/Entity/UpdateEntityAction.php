<?php

namespace BlackParadise\LaravelAdmin\Http\Actions\Entity;

use BlackParadise\LaravelAdmin\Core\Models\BPModel;
use BlackParadise\LaravelAdmin\Http\Actions\Entity\Interface\UpdateEntityInterface;
use BlackParadise\LaravelAdmin\Http\Requests\UpdateAbstractEntityRequest;
use Illuminate\Http\RedirectResponse;

class UpdateEntityAction implements UpdateEntityInterface
{
    private BPModel $BPModel;

    public function __construct(BPModel $BPModel)
    {
        $this->BPModel = $BPModel;
    }

    /**
     * @param int $id
     * @param UpdateAbstractEntityRequest $request
     * @return RedirectResponse
     */
    public function __invoke(int $id, UpdateAbstractEntityRequest $request): RedirectResponse
    {
        $this->BPModel->updateEntity($id, $request->validated());

        return redirectToIndexModelPage($this->BPModel->name);
    }
}
