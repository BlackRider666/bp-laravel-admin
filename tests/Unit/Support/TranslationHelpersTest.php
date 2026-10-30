<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Unit\Support;

use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Fields\FieldContract;
use BlackParadise\CoreAdmin\Domain\Fields\EmbedStrategy;
use BlackParadise\CoreAdmin\Domain\Validation\RuleSet;
use BlackParadise\CoreAdmin\Domain\ValueObjects\EntityCapabilities;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Support\Facades\Lang;

final class TranslationHelpersTest extends TestCase
{
    protected function tearDown(): void
    {
        $this->app->forgetInstance('translator');
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // bp_field_label
    // -----------------------------------------------------------------------

    public function test_bp_field_label_returns_translation_when_key_exists(): void
    {
        Lang::addLines(['entities.users.email' => 'Email Address'], 'en', 'bpadmin');

        $definition = $this->makeDefinition('users');
        $field      = $this->makeField('email', 'Fallback Email Entity');

        self::assertSame('Email Address', bp_field_label($definition, $field));
    }

    public function test_bp_field_label_falls_back_to_field_label_when_missing(): void
    {
        $definition = $this->makeDefinition('users');
        $field      = $this->makeField('phone', 'Phone Number');

        self::assertSame('Phone Number', bp_field_label($definition, $field));
    }

    // -----------------------------------------------------------------------
    // bp_entity_label
    // -----------------------------------------------------------------------

    public function test_bp_entity_label_returns_translation_when_key_exists(): void
    {
        Lang::addLines(['entities.users._label' => 'User Management'], 'en', 'bpadmin');

        $definition = $this->makeDefinition('users', 'Fallback Users Label');

        self::assertSame('User Management', bp_entity_label($definition));
    }

    public function test_bp_entity_label_falls_back_to_definition_label(): void
    {
        $definition = $this->makeDefinition('orders', 'Orders');

        self::assertSame('Orders', bp_entity_label($definition));
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeField(string $name, string $label): FieldContract
    {
        return new class ($name, $label) implements FieldContract {
            public function __construct(
                private readonly string $fieldName,
                private readonly string $fieldLabel,
            ) {}

            public function name(): string
            {
                return $this->fieldName;
            }
            public function label(): string
            {
                return $this->fieldLabel;
            }
            public function type(): string
            {
                return '';
            }
            /** @return list<string> */
            public function rules(): array
            {
                return [];
            }

            public function ruleSet(): RuleSet
            {
                return new RuleSet([]);
            }

            public function visibleOnList(): bool
            {
                return false;
            }

            public function visibleOnForm(): bool
            {
                return false;
            }

            public function visibleOnShow(): bool
            {
                return false;
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

            public function writable(): bool
            {
                return true;
            }

            public function isRelation(): bool
            {
                return false;
            }

            public function isSideEffect(): bool
            {
                return false;
            }

            public function embedStrategy(): EmbedStrategy
            {
                return EmbedStrategy::None;
            }

            public function pointsBackTo(string $hostModelClass): bool
            {
                return false;
            }

            /**
             * @param array<string> $locales
             * @return array<string, list<string>>
             */
            public function expandRules(array $locales, string $context): array
            {
                return [$this->name() => array_values($this->ruleSet()->toArray())];
            }
        };
    }

    private function makeDefinition(string $name, string $label = ''): EntityDefinitionContract
    {
        return new class ($name, $label) implements EntityDefinitionContract {
            public function __construct(
                private readonly string $entityName,
                private readonly string $entityLabel,
            ) {}

            public function name(): string
            {
                return $this->entityName;
            }
            public function label(): string
            {
                return $this->entityLabel;
            }
            public function keyField(): string
            {
                return '';
            }
            public function keyType(): string
            {
                return '';
            }
            public function modelClass(): string
            {
                return '';
            }
            public function fields(): array
            {
                return [];
            }
            public function actions(): array
            {
                return [];
            }
            public function defaultPerPage(): int
            {
                return 0;
            }
            public function searchFields(): array
            {
                return [];
            }

            public function resolveName(): string
            {
                return 'stub';
            }

            public function capabilities(): EntityCapabilities
            {
                return EntityCapabilities::all();
            }

            /** @return array<mixed> */
            public function filters(): array
            {
                return [];
            }

            public function repository(): null
            {
                return null;
            }

            public function mutator(): null
            {
                return null;
            }
        };
    }
}
