<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Http\Presenters\Json;

use BlackParadise\LaravelAdmin\Http\Presenters\DashboardPresenterInterface;
use Symfony\Component\HttpFoundation\Response;

final class JsonDashboardPresenter implements DashboardPresenterInterface
{
    public function index(array $entities): Response
    {
        return response()->json([
            'page'     => 'dashboard',
            'entities' => $entities,
        ]);
    }
}
