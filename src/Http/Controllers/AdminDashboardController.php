<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Http\Controllers;

use BlackParadise\CoreAdmin\Domain\Contracts\Auth\AuthorizationProviderContract;
use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\Http\Presenters\DashboardPresenterInterface;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

/**
 * Thin dashboard controller.
 *
 * Delegates response formatting to {@see DashboardPresenterInterface}.
 */
final class AdminDashboardController extends Controller
{
    public function __construct(
        private readonly EntityDefinitionRegistry $registry,
        private readonly DashboardPresenterInterface $presenter,
        private readonly AuthorizationProviderContract $authorization,
    ) {}

    public function index(): Response
    {
        $entities = array_values(array_map(
            fn(EntityDefinitionContract $def): array => ['name' => $def->name(), 'label' => $def->label()],
            array_filter(
                $this->registry->all(),
                fn(EntityDefinitionContract $def): bool => $this->authorization->can('list', $def),
            ),
        ));

        return $this->presenter->index($entities);
    }
}
