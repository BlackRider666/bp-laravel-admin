<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Persistence\FieldPersisters;

use BlackParadise\CoreAdmin\Domain\Fields\HasManyField;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestComment;
use BlackParadise\LaravelAdmin\Tests\Integration\Persistence\FieldPersisters\Concerns\AssertsPersisterStrategy;
use BlackParadise\LaravelAdmin\Tests\TestCase;

/** Task 2.1 — A18/A19: one HasManyPersister serves replace / merge / append strategies. */
final class HasManyPersisterTest extends TestCase
{
    use AssertsPersisterStrategy;

    public function test_strategy_class_implements_contract_and_extends_abstract_persister(): void
    {
        $this->assertStrategyClassShape('HasManyPersister');
    }

    public function test_strategy_exposes_the_three_persist_hooks(): void
    {
        $this->assertStrategyHooks('HasManyPersister');
    }

    public function test_registry_resolves_has_many_persister_for_replace_strategy(): void
    {
        $this->assertRegistryResolves(HasManyField::make('comments', TestComment::class)->strategy('replace'), 'HasManyPersister');
    }

    public function test_registry_resolves_has_many_persister_for_merge_strategy(): void
    {
        $this->assertRegistryResolves(HasManyField::make('comments', TestComment::class)->strategy('merge'), 'HasManyPersister');
    }

    public function test_registry_resolves_has_many_persister_for_append_strategy(): void
    {
        $this->assertRegistryResolves(HasManyField::make('comments', TestComment::class)->strategy('append'), 'HasManyPersister');
    }
}
