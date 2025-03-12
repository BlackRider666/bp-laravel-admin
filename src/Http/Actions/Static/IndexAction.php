<?php

namespace BlackParadise\LaravelAdmin\Http\Actions\Static;

use BlackParadise\LaravelAdmin\Core\Presenters\DashboardPresenter;

class IndexAction
{
    private DashboardPresenter $dashboardPresenter;

    public function __construct(DashboardPresenter $dashboardPresenter)
    {
        $this->dashboardPresenter = $dashboardPresenter;
    }

    public function __invoke()
    {
        return $this->dashboardPresenter->getIndexPage();
    }
}
