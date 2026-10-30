<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Actions;

use BlackParadise\CoreAdmin\Domain\Contracts\Action\ActionContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Action\ActionResolverContract;
use BlackParadise\CoreAdmin\Domain\Exceptions\InvalidDefinitionException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;

/** Resolves action class-strings through the service container (constructor DI). */
final readonly class ContainerActionResolver implements ActionResolverContract
{
    public function __construct(
        private Container $container,
    ) {}

    public function resolve(string $actionClass): ActionContract
    {
        try {
            $action = $this->container->make($actionClass);
        } catch (BindingResolutionException $e) {
            throw new InvalidDefinitionException(
                sprintf('Action class %s cannot be resolved: %s', $actionClass, $e->getMessage()),
                0,
                $e,
            );
        }

        if (!$action instanceof ActionContract) {
            throw new InvalidDefinitionException(
                sprintf('Action class %s must implement %s.', $actionClass, ActionContract::class),
            );
        }

        return $action;
    }
}
