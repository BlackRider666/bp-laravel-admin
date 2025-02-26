<?php

namespace BlackParadise\LaravelAdmin\Http\Actions\Entity;

use BlackParadise\LaravelAdmin\Core\Models\BPModel;
use BlackParadise\LaravelAdmin\Core\Presenters\DashboardPresenter;
use BlackParadise\LaravelAdmin\Http\Actions\Entity\Interface\EditEntityInterface;
use Illuminate\View\View;

class EditEntityAction implements EditEntityInterface
{
    private BPModel $BPModel;
    private DashboardPresenter $dashboardPresenter;

    public function __construct(BPModel $BPModel, DashboardPresenter $dashboardPresenter)
    {
        $this->BPModel = $BPModel;
        $this->dashboardPresenter = $dashboardPresenter;
    }

    /**
     * @param int $id
     * @return View
     */
    public function __invoke(int $id): View
    {
        $fields = $this->BPModel->getEditFields();

        $item = $this->BPModel->findQuery($id, $fields);

        return $this->dashboardPresenter->getEditPage($this->BPModel, $item);
    }
}
