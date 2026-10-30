<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Query;

use BlackParadise\CoreAdmin\Domain\Contracts\Fields\FieldContract;
use BlackParadise\CoreAdmin\Domain\Fields\BelongsToManyField;
use BlackParadise\CoreAdmin\Domain\Fields\HasManyField;
use BlackParadise\CoreAdmin\Domain\Fields\HasOneField;
use BlackParadise\CoreAdmin\Domain\Fields\MorphManyField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\CoreAdmin\Domain\Fields\TranslatableField;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\CustomPersister\SlugField;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestComment;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestMorphComment;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestProfile;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestTag;
use BlackParadise\LaravelAdmin\Tests\Integration\Query\Concerns\BuildsSearchableArticles;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Closure;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Task 2.3 — FieldQueryRegistry (mirrors FieldPersisterRegistry): field type -> FieldQueryStrategyContract.
 *
 * Pinned API (namespace BlackParadise\LaravelAdmin\Infrastructure\Persistence\Query):
 *   final class FieldQueryRegistry { register(string $type, FieldQueryStrategyContract $strategy): void;
 *                                    for(FieldContract $field): FieldQueryStrategyContract; }
 * Class names are strings: the classes do not exist before Task 2.4.
 */
final class FieldQueryRegistryTest extends TestCase
{
    use BuildsSearchableArticles;


    private const NS = 'BlackParadise\\LaravelAdmin\\Infrastructure\\Persistence\\Query\\';

    protected function setUp(): void
    {
        parent::setUp();
        $this->stubQueryHasher();
    }

    private function requireRegistry(): mixed
    {
        self::assertTrue(class_exists(self::NS . 'FieldQueryRegistry'), 'FieldQueryRegistry must exist');

        return app()->make(self::NS . 'FieldQueryRegistry');
    }

    public function test_registry_is_a_shared_container_singleton(): void
    {
        $registry = $this->requireRegistry();

        self::assertSame($registry, $this->requireRegistry());
    }

    public function test_text_field_resolves_to_column_strategy(): void
    {
        self::assertSame(self::NS . 'ColumnQueryStrategy', get_debug_type($this->requireRegistry()->for(TextField::make('name'))));
    }

    public function test_unknown_custom_field_type_falls_back_to_column_strategy(): void
    {
        self::assertSame(self::NS . 'ColumnQueryStrategy', get_debug_type($this->requireRegistry()->for(SlugField::make('slug'))));
    }

    public function test_translatable_field_resolves_to_translatable_strategy(): void
    {
        self::assertSame(self::NS . 'TranslatableQueryStrategy', get_debug_type($this->requireRegistry()->for(TranslatableField::make('title'))));
    }

    /** @return array<string, array{0: Closure():FieldContract}> */
    public static function notQueryableFields(): array
    {
        return [
            'has_many' => [fn(): HasManyField => HasManyField::make('comments', TestComment::class)],
            'has_one' => [fn(): HasOneField => HasOneField::make('profile', TestProfile::class)],
            'belongs_to_many' => [fn(): BelongsToManyField => BelongsToManyField::make('tags', TestTag::class)],
            'morph_many' => [fn(): MorphManyField => MorphManyField::make('morphComments', TestMorphComment::class)],
        ];
    }

    #[DataProvider('notQueryableFields')]
    public function test_side_effect_relation_resolves_to_not_queryable_strategy(Closure $makeField): void
    {
        self::assertSame(self::NS . 'NotQueryableStrategy', get_debug_type($this->requireRegistry()->for($makeField())));
    }

    public function test_registered_strategy_is_resolved_for_its_type(): void
    {
        $registry = $this->requireRegistry();
        self::assertTrue(interface_exists(self::NS . 'FieldQueryStrategyContract'), 'FieldQueryStrategyContract must exist');
        $strategy = Mockery::mock(self::NS . 'FieldQueryStrategyContract');

        $registry->register('slug', $strategy);

        self::assertSame($strategy, $registry->for(SlugField::make('slug')));
    }

    public function test_registering_a_type_does_not_affect_other_types(): void
    {
        $registry = $this->requireRegistry();
        self::assertTrue(interface_exists(self::NS . 'FieldQueryStrategyContract'), 'FieldQueryStrategyContract must exist');

        $registry->register('slug', Mockery::mock(self::NS . 'FieldQueryStrategyContract'));

        self::assertSame(self::NS . 'ColumnQueryStrategy', get_debug_type($registry->for(TextField::make('name'))));
    }

    public function test_later_registration_overrides_earlier_one(): void
    {
        $registry = $this->requireRegistry();
        self::assertTrue(interface_exists(self::NS . 'FieldQueryStrategyContract'), 'FieldQueryStrategyContract must exist');
        $first = Mockery::mock(self::NS . 'FieldQueryStrategyContract');
        $second = Mockery::mock(self::NS . 'FieldQueryStrategyContract');

        $registry->register('slug', $first);
        $registry->register('slug', $second);

        self::assertSame($second, $registry->for(SlugField::make('slug')));
    }
}
