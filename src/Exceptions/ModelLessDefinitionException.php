<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Exceptions;

use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use LogicException;

/**
 * Thrown when Eloquent infrastructure is asked to touch a definition that
 * declares no Eloquent model (a model-less definition).
 */
final class ModelLessDefinitionException extends LogicException
{
    public static function forDefinition(EntityDefinitionContract $definition): self
    {
        return new self(sprintf('Model-less definition "%s" has no Eloquent model.', $definition->name()));
    }
}
