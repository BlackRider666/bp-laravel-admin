<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Persistence\FieldPersisters;

use BlackParadise\CoreAdmin\Domain\Fields\MorphToField;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItem;
use BlackParadise\LaravelAdmin\Tests\Integration\Persistence\FieldPersisters\Concerns\AssertsPersisterStrategy;
use BlackParadise\LaravelAdmin\Tests\TestCase;

/** Task 2.1 — A18/A19: MorphToPersister strategy is a registered FieldPersister for its field type. */
final class MorphToPersisterTest extends TestCase
{
    use AssertsPersisterStrategy;

    public function test_strategy_class_implements_contract_and_extends_abstract_persister(): void
    {
        $this->assertStrategyClassShape('MorphToPersister');
    }

    public function test_strategy_exposes_the_three_persist_hooks(): void
    {
        $this->assertStrategyHooks('MorphToPersister');
    }

    public function test_registry_resolves_strategy_for_field_type(): void
    {
        $this->assertRegistryResolves(MorphToField::make('subject', TestItem::class), 'MorphToPersister');
    }
}
