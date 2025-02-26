<?php

namespace BlackParadise\LaravelAdmin\Http\Actions\Entity;

use BlackParadise\LaravelAdmin\Core\Models\BPModel;
use BlackParadise\LaravelAdmin\Core\Presenters\DashboardPresenter;
use BlackParadise\LaravelAdmin\Http\Actions\Entity\Interface\ShowEntityInterface;
use Illuminate\View\View;

class ShowEntityAction implements ShowEntityInterface
{
    private BPModel $BPModel;
    private DashboardPresenter $dashboardPresenter;

    public function __construct(BPModel $BPModel, DashboardPresenter $dashboardPresenter)
    {
        $this->dashboardPresenter = $dashboardPresenter;
        $this->BPModel = $BPModel;
    }

    /**
     * @param int $id
     * @return View
     */
    public function __invoke(int $id): View
    {
        $item = $this->BPModel->findQuery($id, $this->BPModel->showPageFields);

        return $this->dashboardPresenter->getShowPage($item, $this->BPModel->showPageFields, $this->BPModel->name);
    }
}
