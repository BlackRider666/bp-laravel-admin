<?php

namespace BlackParadise\LaravelAdmin\Http\Actions\Entity;

use BlackParadise\LaravelAdmin\Core\Models\BPModel;
use BlackParadise\LaravelAdmin\Core\Presenters\DashboardPresenter;
use BlackParadise\LaravelAdmin\Http\Actions\Entity\Interface\CreateEntityInterface;
use Illuminate\View\View;

class CreateEntityAction implements CreateEntityInterface
{
    private BPModel $BPModel;
    private DashboardPresenter $dashboardPresenter;

    public function __construct(BPModel $BPModel, DashboardPresenter $dashboardPresenter)
    {
        $this->BPModel = $BPModel;
        $this->dashboardPresenter = $dashboardPresenter;
    }

    /**
     * @return View
     */
    public function __invoke(): View
    {
        return $this->dashboardPresenter->getCreatePage($this->BPModel);
    }
}
