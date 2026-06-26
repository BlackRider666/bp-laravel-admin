<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Infrastructure;

use BlackParadise\CoreAdmin\Domain\Contracts\Entity\RelationOptionsProviderContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Fields\RelationFieldContract;
use BlackParadise\CoreAdmin\Domain\Validation\RuleSet;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesRelationFixtures;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesTestItemTable;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestTag;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Closure;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

/**
 * Integration tests for Bug #7 — optionConstraints applied in EloquentRelationOptionsProvider.
 *
 * Verifies that WHERE constraints from RelationFieldContract::optionConstraints()
 * are applied when loading options, so only scoped rows are returned.
 *
 * Uses a mocked RelationFieldContract with optionConstraints() because the
 * vendor copy of bp-admin-core may not yet have the method on concrete fields.
 */
final class EloquentRelationOptionsConstraintsTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTestItemTable;
    use CreatesRelationFixtures;

    private RelationOptionsProviderContract $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestItemFixtures();
        $this->setUpRelationFixtures();

        // Add a 'type' column to test_tags so we can filter on it.
        Schema::table('test_tags', function (Blueprint $t): void {
            $t->string('type')->default('general');
        });

        $this->provider = $this->app->make(RelationOptionsProviderContract::class);
    }

    public function test_returns_all_options_when_no_constraints(): void
    {
        TestTag::create(['name' => 'Alpha', 'type' => 'general']);
        TestTag::create(['name' => 'Beta', 'type' => 'special']);

        $field = $this->makeRelationField(TestTag::class, 'name', []);

        $options = $this->provider->options($field);

        self::assertCount(2, $options);
    }

    public function test_filters_options_by_single_constraint(): void
    {
        TestTag::create(['name' => 'Alpha', 'type' => 'general']);
        TestTag::create(['name' => 'Beta', 'type' => 'special']);
        TestTag::create(['name' => 'Gamma', 'type' => 'general']);

        $field = $this->makeRelationField(
            TestTag::class,
            'name',
            [['column' => 'type', 'value' => 'general']],
        );

        $options = $this->provider->options($field);

        self::assertCount(2, $options);
        $labels = array_column($options, 'label');
        self::assertContains('Alpha', $labels);
        self::assertContains('Gamma', $labels);
        self::assertNotContains('Beta', $labels);
    }

    public function test_returns_empty_when_constraint_matches_nothing(): void
    {
        TestTag::create(['name' => 'Alpha', 'type' => 'general']);

        $field = $this->makeRelationField(
            TestTag::class,
            'name',
            [['column' => 'type', 'value' => 'nonexistent']],
        );

        $options = $this->provider->options($field);

        self::assertSame([], $options);
    }

    /**
     * Build a stub RelationFieldContract that also exposes optionConstraints().
     *
     * Uses an anonymous class rather than createMock() because createMock()
     * requires all methods to be declared in the interface.
     *
     * @param list<array{column: string, value: mixed}> $constraints
     */
    private function makeRelationField(
        string $target,
        string $displayField,
        array $constraints,
    ): RelationFieldContract {
        return new class ($target, $displayField, $constraints) implements RelationFieldContract {
            /**
             * @param list<array{column: string, value: mixed}> $constraintList
             */
            public function __construct(
                private readonly string $targetClass,
                private readonly string $display,
                private readonly array $constraintList,
            ) {}

            public function target(): string
            {
                return $this->targetClass;
            }
            public function displayField(): string
            {
                return $this->display;
            }
            /** @return list<array{column: string, value: mixed}> */
            public function optionConstraints(): array
            {
                return $this->constraintList;
            }

            // --- FieldContract stubs ---
            public function name(): string
            {
                return 'field';
            }
            public function label(): string
            {
                return 'Field';
            }
            public function type(): string
            {
                return 'belongs_to';
            }
            /** @return array<string, mixed> */
            public function rules(): array
            {
                return [];
            }
            public function ruleSet(): RuleSet
            {
                return new RuleSet();
            }
            public function writable(): bool
            {
                return true;
            }
            public function visibleOnList(): bool
            {
                return true;
            }
            public function visibleOnForm(): bool
            {
                return true;
            }
            public function visibleOnShow(): bool
            {
                return true;
            }
            public function isSortable(): bool
            {
                return false;
            }
            public function isFilterable(): bool
            {
                return false;
            }
            /** @return array<string, mixed> */
            public function meta(): array
            {
                return [];
            }

            // --- RelationFieldContract stubs ---
            public function relationKind(): string
            {
                return 'belongsTo';
            }
            public function multiple(): bool
            {
                return false;
            }
            public function createInline(): bool
            {
                return false;
            }
            public function relationName(): string
            {
                return 'relation';
            }
            public function isEmbedded(): bool
            {
                return false;
            }
            public function isOwned(): bool
            {
                return false;
            }
            public function embeddedDefinition(): ?string
            {
                return null;
            }
            /** @return array<string, mixed> */
            public function state(): array
            {
                return [];
            }

            public function hasDisplayCallback(): bool
            {
                return false;
            }

            public function displayCallback(): ?Closure
            {
                return null;
            }

            /** @return list<string> */
            public function displayEagerLoad(): array
            {
                return [];
            }

            public function displayOrderColumn(): ?string
            {
                return null;
            }

            public function resolveDisplayLabel(array $row, string $fallbackField): string
            {
                $value = $row[$fallbackField] ?? '';
                return is_scalar($value) ? (string) $value : '';
            }
        };
    }
}
