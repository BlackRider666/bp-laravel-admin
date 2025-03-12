<?php

namespace BlackParadise\LaravelAdmin\Http\Actions\Entity;

use BlackParadise\LaravelAdmin\Core\Models\BPModel;
use BlackParadise\LaravelAdmin\Core\Presenters\DashboardPresenter;
use BlackParadise\LaravelAdmin\Http\Actions\Interfaces\Entity\ShowEntityInterface;
use BlackParadise\LaravelAdmin\Http\Actions\Traits\HandlesEntityAuthorization;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\View\View;
use Inertia\Response;

class ShowEntityAction implements ShowEntityInterface
{
    use HandlesEntityAuthorization;

    private BPModel $BPModel;
    private DashboardPresenter $dashboardPresenter;

    public function __construct(BPModel $BPModel, DashboardPresenter $dashboardPresenter)
    {
        $this->dashboardPresenter = $dashboardPresenter;
        $this->BPModel = $BPModel;
    }

    /**
     * @param int $id
     * @return Response|View
     * @throws AuthorizationException
     */
    public function __invoke(int $id): Response|View
    {
        $item = $this->BPModel->findQuery($id, $this->BPModel->showPageFields);

        $this->authorizeAction('view',$item);

        return $this->dashboardPresenter->getShowPage($item, $this->BPModel->showPageFields, $this->BPModel->name);
    }
}
