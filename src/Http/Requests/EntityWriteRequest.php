<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Http\Requests;

use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Fields\FieldContract;
use BlackParadise\CoreAdmin\Domain\Fields\MorphToField;
use BlackParadise\CoreAdmin\Domain\Fields\RelationFieldTypes;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared request for store/update.
 *
 * HTTP-level concern: only fields declared on the EntityDefinition are
 * forwarded to the use case. Field-level rules (required / max / unique / …)
 * remain in the use-case layer via ValidationProviderContract — duplicating
 * them here would couple the framework adapter to domain validation.
 */
final class EntityWriteRequest extends FormRequest
{
    private ?EntityDefinitionContract $cachedDefinition = null;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Returns input restricted to writable fields declared on the definition.
     *
     * A field is included when it is either writable() OR a side-effect relation
     * (has_many, has_one, belongs_to_many, morph_many, morph_file). Side-effect
     * relations bypass the column-level writable() check because they go through
     * the dedicated relation writer pipeline (pivot sync / child upsert).
     *
     * @return array<string, mixed>
     */
    public function attributesForWrite(): array
    {
        $definition = $this->definition();

        $allowed = array_values(array_map(
            fn(FieldContract $f): string => $f->name(),
            array_filter(
                $definition->fields(),
                fn(FieldContract $f): bool => $f->writable() || RelationFieldTypes::isSideEffect($f->type()),
            ),
        ));

        // morphTo contributes two real columns ({name}_type / {name}_id) that are
        // not field names themselves — admit them so the picker's submission survives.
        foreach ($definition->fields() as $f) {
            if ($f instanceof MorphToField) {
                $allowed = array_merge($allowed, $f->morphColumns());
            }
        }

        return $this->only($allowed);
    }

    public function definition(): EntityDefinitionContract
    {
        return $this->cachedDefinition ??= $this->container
            ->make(EntityDefinitionRegistry::class)
            ->get((string) $this->route('entity'));
    }
}
