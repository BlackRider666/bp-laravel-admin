<?php

namespace BlackParadise\LaravelAdmin\Http\Actions\Entity;

use BlackParadise\LaravelAdmin\Core\Models\BPModel;
use BlackParadise\LaravelAdmin\Http\Actions\Interfaces\Entity\DeleteEntityInterface;
use BlackParadise\LaravelAdmin\Http\Actions\Traits\HandlesEntityAuthorization;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;

class DeleteEntityAction implements DeleteEntityInterface
{
    use HandlesEntityAuthorization;

    private BPModel $BPModel;

    public function __construct(BPModel $BPModel)
    {
        $this->BPModel = $BPModel;
    }

    /**
     * @param int $id
     * @return RedirectResponse
     * @throws AuthorizationException
     */
    public function __invoke(int $id): RedirectResponse
    {
        $model = $this->BPModel->findQuery($id);

        $this->authorizeAction('delete', $model);

        $this->BPModel->delete($model);

        return redirectToIndexModelPage($this->BPModel->name);
    }
}
