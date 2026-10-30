<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\Actions;

use BlackParadise\CoreAdmin\Domain\Action\ActionContext;
use BlackParadise\CoreAdmin\Domain\ValueObjects\EntityKey;

/** Container singleton the test actions report into (also proves the resolver autowires actions). */
final class ActionProbe
{
    /** @var list<array{action: string, scope: string, keys: list<int|string>, input: array<string, mixed>}> */
    public array $calls = [];

    public function record(string $action, ActionContext $context): void
    {
        $this->calls[] = [
            'action' => $action,
            'scope' => $context->scope->value,
            'keys' => array_map(static fn(EntityKey $key): int|string => $key->value, $context->keys),
            'input' => $context->input,
        ];
    }
}
