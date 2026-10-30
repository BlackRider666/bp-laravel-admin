<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Core;

use BlackParadise\CoreAdmin\Domain\Contracts\Action\ActionContract;
use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Fields\FieldContract;
use BlackParadise\CoreAdmin\Domain\Fields\Base\AbstractRelationField;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\Query\FieldQueryRegistry;
use Closure;

/**
 * Collects (never throws) violations of the registered entity definitions. Shared by the boot guard
 * ({@see \BlackParadise\LaravelAdmin\Providers\EntityDiscoveryServiceProvider}) and `bpadmin:doctor`.
 *
 * Violation shape: {definition: registry name, subject: offending member, rule: stable id, message}.
 */
final readonly class DefinitionValidator
{
    public const RULE_MODEL_LESS_WRITABLE = 'model-less-writable';
    public const RULE_MODEL_LESS_RELATION_TARGET = 'model-less-relation-target';
    public const RULE_MODEL_LESS_EMBEDDED_CHILD = 'model-less-embedded-child';
    public const RULE_NON_QUERYABLE_FIELD = 'non-queryable-field';
    public const RULE_UNREGISTERED_EMBEDDED = 'unregistered-embedded';
    public const RULE_UNKNOWN_ACTION = 'unknown-action';

    /** Rules enforced at boot (throwing). */
    public const BOOT_RULES = [
        self::RULE_MODEL_LESS_WRITABLE,
        self::RULE_MODEL_LESS_RELATION_TARGET,
        self::RULE_MODEL_LESS_EMBEDDED_CHILD,
        self::RULE_NON_QUERYABLE_FIELD,
        self::RULE_UNREGISTERED_EMBEDDED,
    ];

    /** @param Closure(): FieldQueryRegistry $queries lazy: resolving the registry at boot must not freeze locale wiring */
    public function __construct(private Closure $queries) {}

    /** @return list<array{definition: string, subject: string, rule: string, message: string}> */
    public function validate(EntityDefinitionRegistry $registry): array
    {
        $fields = $this->collectFields($registry);

        return [
            ...$this->modelLessViolations($registry, $fields),
            ...$this->nonQueryableViolations($registry, $fields),
            ...$this->unregisteredEmbeddedViolations($registry, $fields),
            ...$this->unknownActionViolations($registry),
        ];
    }

    /**
     * Violations of the {@see BOOT_RULES} only (fields() is built once per definition).
     *
     * @return list<array{definition: string, subject: string, rule: string, message: string}>
     */
    public function bootViolations(EntityDefinitionRegistry $registry): array
    {
        $fields = $this->collectFields($registry);

        return [
            ...$this->modelLessViolations($registry, $fields),
            ...$this->nonQueryableViolations($registry, $fields),
            ...$this->unregisteredEmbeddedViolations($registry, $fields),
        ];
    }

    /** @return array<string, list<FieldContract>> */
    private function collectFields(EntityDefinitionRegistry $registry): array
    {
        $fields = [];
        foreach ($registry->all() as $name => $definition) {
            $fields[$name] = array_values($definition->fields());
        }

        return $fields;
    }

    /**
     * @param array<string, list<FieldContract>>|null $fields pre-built fields() per definition name (built here when omitted)
     * @return list<array{definition: string, subject: string, rule: string, message: string}>
     */
    public function modelLessViolations(EntityDefinitionRegistry $registry, ?array $fields = null): array
    {
        $fields ??= $this->collectFields($registry);
        $modelLess = [];
        foreach ($registry->all() as $name => $definition) {
            if ($definition->modelClass() === null) {
                $modelLess[$name] = $definition;
            }
        }
        if ($modelLess === []) {
            return [];
        }

        $violations = [];

        foreach ($modelLess as $name => $definition) {
            $capabilities = $definition->capabilities();
            $writable = $capabilities->create || $capabilities->update || $capabilities->delete;
            if ($writable && $definition->mutator() === null) {
                $violations[] = [
                    'definition' => $name,
                    'subject' => $name,
                    'rule' => self::RULE_MODEL_LESS_WRITABLE,
                    'message' => sprintf(
                        'Model-less definition "%s" is writable but declares no mutator(): provide a mutator() or use readOnly() capabilities.',
                        $name,
                    ),
                ];
            }
        }

        foreach (array_keys($registry->all()) as $name) {
            foreach ($fields[$name] as $field) {
                if (!$field instanceof AbstractRelationField) {
                    continue;
                }
                $references = [
                    self::RULE_MODEL_LESS_RELATION_TARGET => ['relation target', $field->target()],
                    self::RULE_MODEL_LESS_EMBEDDED_CHILD => ['embedded child', $field->embeddedDefinition()],
                ];
                foreach ($references as $rule => [$role, $class]) {
                    if ($class === null) {
                        continue;
                    }
                    if (!is_subclass_of($class, EntityDefinition::class)) {
                        continue;
                    }
                    $referenced = $registry->forClassOrNull($class);
                    if (!$referenced instanceof EntityDefinitionContract) {
                        continue;
                    }
                    if (!in_array($referenced, $modelLess, true)) {
                        continue;
                    }
                    $violations[] = [
                        'definition' => $name,
                        'subject' => $field->name(),
                        'rule' => $rule,
                        'message' => sprintf(
                            'Definition "%s" field "%s" uses the model-less definition "%s" as %s; model-less definitions cannot be Eloquent relation targets or embedded children.',
                            $name,
                            $field->name(),
                            $referenced->name(),
                            $role,
                        ),
                    ];
                }
            }
        }

        return $violations;
    }

    /**
     * @param array<string, list<FieldContract>>|null $fields pre-built fields() per definition name (built here when omitted)
     * @return list<array{definition: string, subject: string, rule: string, message: string}>
     */
    public function nonQueryableViolations(EntityDefinitionRegistry $registry, ?array $fields = null): array
    {
        $fields ??= $this->collectFields($registry);
        $queries = ($this->queries)();
        $violations = [];
        foreach ($registry->all() as $name => $definition) {
            foreach ($queries->nonQueryableFields($definition, $fields[$name]) as $found) {
                $violations[] = [
                    'definition' => $name,
                    'subject' => $found['field'],
                    'rule' => self::RULE_NON_QUERYABLE_FIELD,
                    'message' => sprintf(
                        'Definition "%s": %s',
                        $name,
                        $queries->nonQueryableMessage($definition, $found),
                    ),
                ];
            }
        }

        return $violations;
    }

    /**
     * @param array<string, list<FieldContract>>|null $fields pre-built fields() per definition name (built here when omitted)
     * @return list<array{definition: string, subject: string, rule: string, message: string}>
     */
    public function unregisteredEmbeddedViolations(EntityDefinitionRegistry $registry, ?array $fields = null): array
    {
        $fields ??= $this->collectFields($registry);
        $violations = [];
        foreach (array_keys($registry->all()) as $name) {
            foreach ($fields[$name] as $field) {
                if (!$field instanceof AbstractRelationField) {
                    continue;
                }
                $class = $field->embeddedDefinition();
                if ($class === null) {
                    continue;
                }
                if ($registry->forClassOrNull($class) instanceof EntityDefinitionContract) {
                    continue;
                }
                $violations[] = [
                    'definition' => $name,
                    'subject' => $field->name(),
                    'rule' => self::RULE_UNREGISTERED_EMBEDDED,
                    'message' => sprintf(
                        'Definition "%s" field "%s" embeds %s, which is not registered in the entity registry.',
                        $name,
                        $field->name(),
                        $class,
                    ),
                ];
            }
        }

        return $violations;
    }

    /** @return list<array{definition: string, subject: string, rule: string, message: string}> */
    private function unknownActionViolations(EntityDefinitionRegistry $registry): array
    {
        $violations = [];
        foreach ($registry->all() as $name => $definition) {
            foreach ($definition->actions() as $actionClass) {
                // Declared type is class-string<ActionContract>, but definitions are user-authored: verify at runtime.
                if (is_a($actionClass, ActionContract::class, true)) { // @phpstan-ignore function.alreadyNarrowedType
                    continue;
                }
                $violations[] = [
                    'definition' => $name,
                    'subject' => $actionClass,
                    'rule' => self::RULE_UNKNOWN_ACTION,
                    'message' => sprintf(
                        'Definition "%s" lists action class %s, which does not exist or does not implement ActionContract.',
                        $name,
                        $actionClass,
                    ),
                ];
            }
        }

        return $violations;
    }
}
