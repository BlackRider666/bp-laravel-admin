<?php

namespace BlackParadise\LaravelAdmin\Http\Actions\Entity;

use BlackParadise\LaravelAdmin\Core\Models\BPModel;
use BlackParadise\LaravelAdmin\Http\Actions\Entity\Interface\DeleteEntityInterface;
use Illuminate\Http\RedirectResponse;

class DeleteEntityAction implements DeleteEntityInterface
{
    private BPModel $BPModel;

    public function __construct(BPModel $BPModel)
    {
        $this->BPModel = $BPModel;
    }

    /**
     * @param int $id
     * @return RedirectResponse
     */
    public function __invoke(int $id): RedirectResponse
    {
        $this->BPModel->delete($id);

        return redirectToIndexModelPage($this->BPModel->name);
    }
}
