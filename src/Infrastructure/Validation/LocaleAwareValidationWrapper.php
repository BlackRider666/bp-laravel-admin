<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Validation;

use BlackParadise\CoreAdmin\Application\UseCases\Entity\RuleBuilder;
use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\Contracts\LocaleProviderContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Validation\ValidationProviderContract;
use BlackParadise\CoreAdmin\Domain\Fields\MorphToField;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Decorator for ValidationProviderContract that rebuilds validation rules
 * using the locale-aware {@see RuleBuilder} instance-API.
 *
 * Purpose: the core use cases (CreateRecordUseCase / UpdateRecordUseCase) build
 * rules via the legacy static {@see RuleBuilder::fromDefinition()} which does
 * NOT expand TranslatableField rules into per-locale dot-notation keys. This
 * wrapper intercepts the validate() call, discards the incoming (locale-unaware)
 * rules, and replaces them with rules rebuilt by `new RuleBuilder($locales)->build()`.
 *
 * Wiring: UseCaseFactory creates one instance per use-case call, binding the
 * concrete EntityDefinitionContract so the wrapper has access to all fields.
 */
final readonly class LocaleAwareValidationWrapper implements ValidationProviderContract
{
    /**
     * @param string $context Validation context: 'create' (default) or 'update'.
     *                        In 'update' context, fields absent from the payload have
     *                        their 'required' rule relaxed to 'sometimes'.
     * @param list<string> $skipFields Field names to drop from the rule set before
     *                                 delegating to the inner provider. Used for embedded
     *                                 hasMany/morphMany children to skip the back-FK field
     *                                 that the ORM assigns automatically on write.
     */
    public function __construct(
        private ValidationProviderContract $inner,
        private LocaleProviderContract $localeProvider,
        private EntityDefinitionContract $definition,
        private string $context = 'create',
        /** @var list<string> */
        private array $skipFields = [],
    ) {}

    /**
     * Validate $data against locale-expanded rules built from the definition.
     *
     * The $rules parameter is intentionally ignored: it is produced by the
     * legacy static RuleBuilder::fromDefinition() inside the core use case and
     * therefore contains no per-locale expansions for TranslatableFields.
     *
     * Requires bp-admin-core >= 1.0.2 which guarantees the instance-API
     * RuleBuilder::build(). The composer constraint "^1.0.2" enforces this.
     *
     * @param array<string, mixed> $data
     * @param array<string, array<string>> $rules Ignored — superseded by locale-aware rebuild.
     */
    public function validate(array $data, array $rules): void
    {
        // RuleBuilder::build() is the new instance-API (bp-admin-core >= 1.0.2).
        // availableLocales() returns array<string>; RuleBuilder expects list<string>.
        $builder     = new RuleBuilder(array_values($this->localeProvider->availableLocales()), $this->context);
        $localeRules = $builder->build($this->definition, array_keys($data));

        $localeRules = $this->rewriteRelationExists($localeRules);
        $localeRules = $this->rewriteMorphToRules($localeRules, $this->context, $data);

        foreach ($this->skipFields as $skip) {
            unset($localeRules[$skip]);
        }

        $this->inner->validate($data, $localeRules);
    }

    /**
     * Rewrite morph_to field rules from the field-name key to per-column keys,
     * and add A13 membership + existence rules.
     *
     * The RuleBuilder emits rules keyed on the field name (e.g. `commentable`),
     * but morph payloads carry the two real columns (`commentable_type`,
     * `commentable_id`). Validation against the field name always fails because
     * neither column is submitted under that key.
     *
     * Transformation (A14 + A13):
     *   commentable: [required]  →  commentable_type: [required, string, in:<allowed>]
     *                               commentable_id:   [required, <closure: exists check>]
     *
     * A13 rules:
     *   - `in:<allowed>` on `_type`: accepted values are each class's getMorphClass()
     *     alias AND the FQCN itself (to accept pre-normalisation values from the UI).
     *   - Closure on `_id`: resolves the submitted `_type` through the Eloquent morph
     *     map, then checks that a row with the given key exists in that model's table.
     *     The check is skipped when type or id is empty (the presence rule covers that).
     *
     * In 'update' context, missing columns are handled with 'sometimes' (matching
     * the partial-update semantics already applied by RuleBuilder for scalar fields).
     *
     * @param array<string, array<int, string>> $rules
     * @param array<string, mixed> $data Full incoming payload (used by closure rules for testability).
     * @return array<string, mixed>
     */
    private function rewriteMorphToRules(array $rules, string $context, array $data): array
    {
        $presentKeys = array_keys($data);

        foreach ($this->definition->fields() as $field) {
            if (!$field instanceof MorphToField) {
                continue;
            }

            $fieldName = $field->name();
            if (!array_key_exists($fieldName, $rules)) {
                continue;
            }

            $fieldRules = $rules[$fieldName];
            unset($rules[$fieldName]);

            $isRequired = in_array('required', $fieldRules, true);
            $typeCol    = $field->typeColumn();
            $idCol      = $field->idColumn();

            // Build list of allowed morph type values for A13 type-membership check.
            // Accept both the morph-map alias (getMorphClass()) AND the raw FQCN so
            // that pre-normalisation values from the UI are not rejected.
            $allowed = [];
            foreach (array_keys($field->morphTypeMap()) as $class) {
                if (class_exists($class)) {
                    $instance  = new $class();
                    $allowed[] = $instance->getMorphClass();
                    $allowed[] = $class;
                }
            }
            $allowed = array_values(array_unique($allowed));

            // In update context, apply partial-update semantics: when the morph
            // columns are absent from the payload, relax required → sometimes.
            if ($context === 'update' && !in_array($typeCol, $presentKeys, true)) {
                $presence = 'sometimes';
            } else {
                $presence = $isRequired ? 'required' : 'nullable';
            }

            $rules[$typeCol] = array_values(array_filter([
                $presence,
                'string',
                $allowed !== [] ? 'in:' . implode(',', $allowed) : null,
            ]));

            $rules[$idCol] = [
                $presence,
                function (string $attribute, mixed $value, Closure $fail) use ($data, $typeCol): void {
                    $type = isset($data[$typeCol]) && is_string($data[$typeCol]) ? $data[$typeCol] : '';
                    if ($type === '' || $value === null || $value === '') {
                        // Presence rule on the sibling column handles emptiness.
                        return;
                    }

                    // Resolve morph-map alias to the actual class, falling back to the
                    // raw value when no alias exists.
                    $class = Relation::getMorphedModel($type) ?? $type;

                    if (!class_exists($class)
                        || !is_subclass_of($class, Model::class)
                        || !$class::query()->whereKey($value)->exists()) {
                        $fail('The selected ' . $attribute . ' is invalid.');
                    }
                },
            ];
        }

        return $rules;
    }

    /**
     * Rewrite the core domain marker `relation_exists:<ModelClass>` into Laravel's
     * `exists:<table>,<key>`. Core emits the marker because it cannot resolve
     * Eloquent table/key names; the adapter resolves them here.
     *
     * Unknown or non-Eloquent classes have their marker silently dropped to avoid
     * leaking an unrecognised rule string into the Laravel validator (which would
     * throw a BadMethodCallException instead of a proper 422).
     *
     * @param array<string, array<int, string>> $rules
     * @return array<string, array<int, string>>
     */
    private function rewriteRelationExists(array $rules): array
    {
        foreach ($rules as $field => $fieldRules) {
            foreach ($fieldRules as $i => $rule) {
                if (!str_starts_with($rule, 'relation_exists:')) {
                    continue;
                }

                $modelClass = substr($rule, strlen('relation_exists:'));

                if (!class_exists($modelClass) || !is_subclass_of($modelClass, Model::class)) {
                    // Unknown class — drop marker; never leak an unrecognised rule to the validator.
                    unset($rules[$field][$i]);
                    continue;
                }

                $model = new $modelClass();
                $rules[$field][$i] = 'exists:' . $model->getTable() . ',' . $model->getKeyName();
            }

            $rules[$field] = array_values($rules[$field]);
        }

        return $rules;
    }
}
