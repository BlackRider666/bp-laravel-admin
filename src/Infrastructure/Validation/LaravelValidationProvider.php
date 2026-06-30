<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Validation;

use BlackParadise\CoreAdmin\Domain\Contracts\Validation\ValidationProviderContract;
use BlackParadise\CoreAdmin\Domain\Exceptions\ValidationException;
use Closure;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

final readonly class LaravelValidationProvider implements ValidationProviderContract
{
    public function __construct(
        private Factory $factory,
    ) {}

    /**
     * @param array<string, mixed> $data
     * @param array<string, array<string>> $rules
     */
    public function validate(array $data, array $rules): void
    {
        $validator = $this->factory->make($data, $this->resolveMarkers($rules, $data));

        if ($validator->fails()) {
            throw new ValidationException($validator->errors()->toArray());
        }
    }

    /**
     * Rewrite the framework-free rule markers emitted by bp-admin-core into native
     * Laravel rules. The core layer cannot resolve Eloquent table/key names or build
     * closures, so it emits markers that the adapter resolves here, right before the
     * validator is built:
     *
     *   relation_exists:<FQCN>            → exists:[<connection>.]<table>,<key>
     *   morph_type_in:<FQCN1>,<FQCN2>,... → in:<alias-or-FQCN list>
     *   morph_exists  (on a <name>_id key) → Closure verifying the morph row exists
     *
     * Unresolvable markers (unknown / non-Eloquent classes) are dropped rather than
     * leaked to the validator, which would otherwise throw a BadMethodCallException
     * (HTTP 500) instead of producing a proper 422.
     *
     * @param array<string, array<string>> $rules
     * @param array<string, mixed> $data Full incoming payload — consulted by the morph closure.
     * @return array<string, array<int, string|Closure>>
     */
    private function resolveMarkers(array $rules, array $data): array
    {
        $resolved = [];

        foreach ($rules as $field => $fieldRules) {
            $out = [];

            foreach ($fieldRules as $rule) {
                if (str_starts_with($rule, 'relation_exists:')) {
                    $existsRule = $this->resolveRelationExists($rule);
                    if ($existsRule !== null) {
                        $out[] = $existsRule;
                    }

                    continue;
                }

                if (str_starts_with($rule, 'morph_type_in:')) {
                    $inRule = $this->resolveMorphTypeIn($rule);
                    if ($inRule !== null) {
                        $out[] = $inRule;
                    }

                    continue;
                }

                if ($rule === 'morph_exists') {
                    $out[] = $this->makeMorphExistsRule($field, $data);

                    continue;
                }

                $out[] = $rule;
            }

            $resolved[$field] = $out;
        }

        return $resolved;
    }

    /**
     * relation_exists:<FQCN> → exists:[<connection>.]<table>,<key>. Returns null when
     * the class is not a resolvable Eloquent model so the caller drops the marker.
     *
     * When the model declares a named connection (getConnectionName() !== null), the
     * table is prefixed with that connection so Laravel's `exists` rule queries the
     * correct database: `exists:<connection>.<table>,<key>`. Default-connection models
     * stay unprefixed: `exists:<table>,<key>`.
     */
    private function resolveRelationExists(string $rule): ?string
    {
        $modelClass = substr($rule, strlen('relation_exists:'));

        if (!class_exists($modelClass) || !is_subclass_of($modelClass, Model::class)) {
            return null;
        }

        $model = new $modelClass();

        $connection = $model->getConnectionName();
        $table = $connection !== null
            ? $connection . '.' . $model->getTable()
            : $model->getTable();

        return 'exists:' . $table . ',' . $model->getKeyName();
    }

    /**
     * morph_type_in:<FQCN1>,<FQCN2>,... → in:<allowed>. The allowed set contains, for
     * each resolvable model class, its morph-map alias (getMorphClass()) AND the raw
     * FQCN so pre-normalisation values from the UI are accepted. Returns null when no
     * class resolves so the caller drops the marker.
     */
    private function resolveMorphTypeIn(string $rule): ?string
    {
        $classes = array_filter(
            explode(',', substr($rule, strlen('morph_type_in:'))),
            static fn(string $class): bool => $class !== '',
        );

        $allowed = [];
        foreach ($classes as $class) {
            if (!is_subclass_of($class, Model::class)) {
                continue;
            }

            $allowed[] = (new $class())->getMorphClass();
            $allowed[] = $class;
        }

        $allowed = array_values(array_unique($allowed));

        if ($allowed === []) {
            return null;
        }

        return 'in:' . implode(',', $allowed);
    }

    /**
     * morph_exists → Closure verifying the polymorphic target row exists. The sibling
     * _type column is derived from the _id key by convention (strip _id → add _type).
     * The submitted type is resolved through the Eloquent morph map; emptiness is left
     * to the presence rule on the sibling column.
     *
     * @param array<string, mixed> $data
     */
    private function makeMorphExistsRule(string $idColumn, array $data): Closure
    {
        $typeColumn = str_ends_with($idColumn, '_id')
            ? substr($idColumn, 0, -3) . '_type'
            : $idColumn . '_type';

        return function (string $attribute, mixed $value, Closure $fail) use ($data, $typeColumn): void {
            $type = isset($data[$typeColumn]) && is_string($data[$typeColumn]) ? $data[$typeColumn] : '';
            if ($type === '' || $value === null || $value === '') {
                // Presence rule on the sibling column handles emptiness.
                return;
            }

            $class = Relation::getMorphedModel($type) ?? $type;

            if (!class_exists($class) || !is_subclass_of($class, Model::class)) {
                $fail('The selected ' . $attribute . ' is invalid.');

                return;
            }

            $model  = new $class();
            $exists = $model->getConnection()
                ->table($model->getTable())
                ->where($model->getKeyName(), '=', $value)
                ->exists();

            if (!$exists) {
                $fail('The selected ' . $attribute . ' is invalid.');
            }
        };
    }
}
