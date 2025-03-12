<?php

namespace BlackParadise\LaravelAdmin\Http\Actions\Entity;

use BlackParadise\LaravelAdmin\Core\Models\BPModel;
use BlackParadise\LaravelAdmin\Core\Presenters\DashboardPresenter;
use BlackParadise\LaravelAdmin\Http\Actions\Interfaces\Entity\EditEntityInterface;
use BlackParadise\LaravelAdmin\Http\Actions\Traits\HandlesEntityAuthorization;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\View\View;
use Inertia\Response;

class EditEntityAction implements EditEntityInterface
{
    use HandlesEntityAuthorization;

    private BPModel $BPModel;
    private DashboardPresenter $dashboardPresenter;

    public function __construct(BPModel $BPModel, DashboardPresenter $dashboardPresenter)
    {
        $this->BPModel = $BPModel;
        $this->dashboardPresenter = $dashboardPresenter;
    }

    /**
     * @param int $id
     * @return Response|View
     * @throws AuthorizationException
     */
    public function __invoke(int $id): Response|View
    {
        $fields = $this->BPModel->getEditFields();
        $item = $this->BPModel->findQuery($id, $fields);

        $this->authorizeAction('update',$item);

        return $this->dashboardPresenter->getEditPage($this->BPModel, $item);
    }
}
