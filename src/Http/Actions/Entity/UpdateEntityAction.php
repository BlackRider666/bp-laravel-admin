<?php

namespace BlackParadise\LaravelAdmin\Http\Actions\Entity;

use BlackParadise\LaravelAdmin\Core\Models\BPModel;
use BlackParadise\LaravelAdmin\Http\Actions\Interface\Entity\UpdateEntityInterface;
use BlackParadise\LaravelAdmin\Http\Actions\Traits\HandlesEntityAuthorization;
use BlackParadise\LaravelAdmin\Http\Requests\Entity\UpdateAbstractEntityRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;

class UpdateEntityAction implements UpdateEntityInterface
{
    use HandlesEntityAuthorization;

    private BPModel $BPModel;

    public function __construct(BPModel $BPModel)
    {
        $this->BPModel = $BPModel;
    }

    /**
     * @param int $id
     * @param UpdateAbstractEntityRequest $request
     * @return RedirectResponse
     * @throws AuthorizationException
     */
    public function __invoke(int $id, UpdateAbstractEntityRequest $request): RedirectResponse
    {
        $fields = $this->BPModel->getEditFields();
        $item = $this->BPModel->findQuery($id, $fields);

        $this->authorizeAction('update',$item);

        $this->BPModel->updateEntity($item, $request->validated());

        return redirectToIndexModelPage($this->BPModel->name);
    }
}
