<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures;

use BlackParadise\CoreAdmin\Domain\Contracts\Auth\AuthorizationProviderContract;
use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;

/** Allows everything except the listed abilities; remembers every ask as "ability:entity". */
final class ScriptedAuthorization implements AuthorizationProviderContract
{
    /** @var list<string> */
    public array $asked = [];

    /** @param list<string> $denied */
    public function __construct(private readonly array $denied = []) {}

    public function can(string $action, EntityDefinitionContract $entityDefinition): bool
    {
        $this->asked[] = $action . ':' . $entityDefinition->name();

        return !in_array($action, $this->denied, true);
    }
}
