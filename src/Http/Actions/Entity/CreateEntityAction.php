<?php

namespace BlackParadise\LaravelAdmin\Http\Actions\Entity;

use BlackParadise\LaravelAdmin\Core\Models\BPModel;
use BlackParadise\LaravelAdmin\Core\Presenters\DashboardPresenter;
use BlackParadise\LaravelAdmin\Http\Actions\Interfaces\Entity\CreateEntityInterface;
use BlackParadise\LaravelAdmin\Http\Actions\Traits\HandlesEntityAuthorization;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\View\View;
use Inertia\Response;

class CreateEntityAction implements CreateEntityInterface
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
     * @return Response|View
     * @throws AuthorizationException
     */
    public function __invoke(): Response|View
    {
        $this->authorizeAction('create');

        return $this->dashboardPresenter->getCreatePage($this->BPModel);
    }
}
