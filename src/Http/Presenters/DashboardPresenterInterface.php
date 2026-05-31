<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Http\Presenters;

use Symfony\Component\HttpFoundation\Response;

interface DashboardPresenterInterface
{
    /** @param array<array{name: string, label: string}> $entities */
    public function index(array $entities): Response;
}
