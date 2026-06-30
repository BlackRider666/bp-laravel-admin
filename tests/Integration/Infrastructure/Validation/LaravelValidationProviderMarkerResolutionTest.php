<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Infrastructure\Validation;

use BlackParadise\CoreAdmin\Domain\Exceptions\ValidationException;
use BlackParadise\LaravelAdmin\Infrastructure\Validation\LaravelValidationProvider;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Factory as ValidationFactory;

/**
 * RED tests (Task 1.3) — A5 / A6 marker resolution in LaravelValidationProvider.
 *
 * Seam chosen: LaravelValidationProvider::validate().
 * Task 1.4 must add marker-resolution logic there (not in a wrapper) so that the
 * use cases can pass raw rule arrays containing markers directly to the provider.
 *
 * Pre-IMPL failure mode: LaravelValidationProvider passes marker strings verbatim
 * to the Laravel validator, which throws BadMethodCallException (unknown rule) → ERROR.
 *
 * Post-IMPL: markers are resolved inside validate() before the validator is built.
 */
final class LaravelValidationProviderMarkerResolutionTest extends TestCase
{
    use RefreshDatabase;

    private LaravelValidationProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('marker_fk_targets', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->timestamps();
        });

        Schema::create('marker_morph_targets', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        $this->provider = new LaravelValidationProvider(resolve(ValidationFactory::class));
    }

    // -------------------------------------------------------------------------
    // relation_exists:<FQCN> — A5
    // -------------------------------------------------------------------------

    /**
     * A5: relation_exists marker with a non-existent FK must produce a
     * ValidationException (HTTP 422), not a BadMethodCallException (500).
     *
     * Pre-IMPL: Laravel rejects the unknown rule string → BadMethodCallException → ERROR → RED.
     * Post-IMPL: marker is resolved to exists:marker_fk_targets,id → ValidationException.
     */
    public function test_relation_exists_marker_with_nonexistent_fk_throws_validation_exception(): void
    {
        $this->expectException(ValidationException::class);

        $this->provider->validate(
            ['target_id' => 99999],
            ['target_id' => ['required', 'relation_exists:' . MarkerFkTarget::class]],
        );
    }

    /**
     * A5: relation_exists marker with an existing FK must pass validation (no exception).
     *
     * Pre-IMPL: BadMethodCallException → ERROR → RED.
     * Post-IMPL: marker is resolved; FK exists → passes.
     */
    public function test_relation_exists_marker_with_existing_fk_passes(): void
    {
        $record = MarkerFkTarget::create(['title' => 'Present']);

        // Must NOT throw — the FK references a real row.
        $this->provider->validate(
            ['target_id' => (int) $record->getKey()],
            ['target_id' => ['required', 'relation_exists:' . MarkerFkTarget::class]],
        );

        $this->addToAssertionCount(1);
    }

    /**
     * A5: relation_exists marker with an empty required value must throw ValidationException.
     *
     * Pre-IMPL: BadMethodCallException → ERROR → RED.
     * Post-IMPL: required rule fires before exists check → ValidationException.
     */
    public function test_relation_exists_marker_with_empty_required_value_throws_validation_exception(): void
    {
        $this->expectException(ValidationException::class);

        $this->provider->validate(
            ['target_id' => ''],
            ['target_id' => ['required', 'relation_exists:' . MarkerFkTarget::class]],
        );
    }

    // -------------------------------------------------------------------------
    // morph_type_in:<FQCN1>,<FQCN2> — A6 type membership
    // -------------------------------------------------------------------------

    /**
     * A6: morph_type_in marker with a type NOT in the allowlist must produce
     * a ValidationException on the _type field.
     *
     * Pre-IMPL: BadMethodCallException (unknown rule) → ERROR → RED.
     * Post-IMPL: marker resolves to in:alias,FQCN → ValidationException for invalid type.
     */
    public function test_morph_type_in_marker_with_invalid_type_throws_validation_exception(): void
    {
        $this->expectException(ValidationException::class);

        $this->provider->validate(
            ['commentable_type' => 'NonExistentClass'],
            ['commentable_type' => ['required', 'morph_type_in:' . MarkerMorphTarget::class]],
        );
    }

    /**
     * A6: morph_type_in marker with a type that equals the FQCN must pass.
     *
     * Pre-IMPL: BadMethodCallException → ERROR → RED.
     * Post-IMPL: marker resolves to in:...,FQCN; FQCN value passes.
     */
    public function test_morph_type_in_marker_with_fqcn_type_passes(): void
    {
        // MarkerMorphTarget::class is always a valid value in morph_type_in:<FQCN>
        $this->provider->validate(
            ['commentable_type' => MarkerMorphTarget::class],
            ['commentable_type' => ['required', 'morph_type_in:' . MarkerMorphTarget::class]],
        );

        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // morph_exists — A6 id existence
    // -------------------------------------------------------------------------

    /**
     * A6: morph_exists marker with a non-existent id must throw ValidationException.
     *
     * The resolver must derive the _type sibling column by convention
     * (strip _id suffix → add _type suffix) and use the submitted type to
     * find the target table.
     *
     * Pre-IMPL: BadMethodCallException → ERROR → RED.
     * Post-IMPL: closure resolves MarkerMorphTarget → checks marker_morph_targets → fails.
     */
    public function test_morph_exists_marker_with_nonexistent_id_throws_validation_exception(): void
    {
        $this->expectException(ValidationException::class);

        $this->provider->validate(
            [
                'commentable_type' => MarkerMorphTarget::class,
                'commentable_id'   => 99999,
            ],
            [
                'commentable_type' => ['required', 'morph_type_in:' . MarkerMorphTarget::class],
                'commentable_id'   => ['required', 'morph_exists'],
            ],
        );
    }

    /**
     * A6: morph_exists marker with an existing id must pass.
     *
     * Pre-IMPL: BadMethodCallException → ERROR → RED.
     * Post-IMPL: closure resolves model; row exists → passes.
     */
    public function test_morph_exists_marker_with_existing_id_passes(): void
    {
        $target = MarkerMorphTarget::create(['name' => 'Present']);

        $this->provider->validate(
            [
                'commentable_type' => MarkerMorphTarget::class,
                'commentable_id'   => (int) $target->getKey(),
            ],
            [
                'commentable_type' => ['required', 'morph_type_in:' . MarkerMorphTarget::class],
                'commentable_id'   => ['required', 'morph_exists'],
            ],
        );

        $this->addToAssertionCount(1);
    }
}

// ---------------------------------------------------------------------------
// Inline Eloquent model fixtures
// ---------------------------------------------------------------------------

/**
 * Minimal model used as a BelongsTo FK target in marker-resolution tests.
 */
class MarkerFkTarget extends Model
{
    protected $table    = 'marker_fk_targets';

    protected $fillable = ['title'];
}

/**
 * Minimal model used as a MorphTo target in marker-resolution tests.
 */
class MarkerMorphTarget extends Model
{
    protected $table    = 'marker_morph_targets';

    protected $fillable = ['name'];
}
