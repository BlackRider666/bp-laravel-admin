<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Persistence\FieldPersisters;

use BlackParadise\CoreAdmin\Domain\Fields\MorphManyField;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestMorphComment;
use BlackParadise\LaravelAdmin\Tests\Integration\Persistence\FieldPersisters\Concerns\AssertsPersisterStrategy;
use BlackParadise\LaravelAdmin\Tests\TestCase;

/** Task 2.1 — A18/A19: MorphManyPersister strategy is a registered FieldPersister for its field type. */
final class MorphManyPersisterTest extends TestCase
{
    use AssertsPersisterStrategy;

    public function test_strategy_class_implements_contract_and_extends_abstract_persister(): void
    {
        $this->assertStrategyClassShape('MorphManyPersister');
    }

    public function test_strategy_exposes_the_three_persist_hooks(): void
    {
        $this->assertStrategyHooks('MorphManyPersister');
    }

    public function test_registry_resolves_strategy_for_field_type(): void
    {
        $this->assertRegistryResolves(MorphManyField::make('morphComments', TestMorphComment::class)->strategy('append'), 'MorphManyPersister');
    }
}
