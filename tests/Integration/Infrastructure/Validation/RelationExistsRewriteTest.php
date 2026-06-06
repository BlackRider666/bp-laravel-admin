<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Infrastructure\Validation;

use BlackParadise\CoreAdmin\Domain\Exceptions\ValidationException;
use BlackParadise\CoreAdmin\Domain\Fields\BelongsToField;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Infrastructure\Locale\ConfigLocaleProvider;
use BlackParadise\LaravelAdmin\Infrastructure\Validation\LaravelValidationProvider;
use BlackParadise\LaravelAdmin\Infrastructure\Validation\LocaleAwareValidationWrapper;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Factory as ValidationFactory;

/**
 * Integration tests for LocaleAwareValidationWrapper::rewriteRelationExists().
 *
 * Spec assertions:
 *   A1 — non-existent BelongsTo FK → ValidationException (HTTP 422), NOT QueryException/500.
 *   A2 — existing FK passes; required-empty → 422; nullable-absent passes.
 */
final class RelationExistsRewriteTest extends TestCase
{
    use RefreshDatabase;

    private LocaleAwareValidationWrapper $wrapper;
    private LocaleAwareValidationWrapper $wrapperNullable;

    protected function setUp(): void
    {
        parent::setUp();

        // Create the target table (referenced by FK)
        Schema::create('relation_targets', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->timestamps();
        });

        $this->wrapper         = $this->makeWrapper(required: true);
        $this->wrapperNullable = $this->makeWrapper(required: false);
    }

    // -------------------------------------------------------------------------
    // A1 — non-existent FK → ValidationException (never QueryException / 500)
    // -------------------------------------------------------------------------

    public function test_nonexistent_fk_throws_validation_not_query_error(): void
    {
        $this->expectException(ValidationException::class);

        $this->wrapper->validate(['target_id' => 99999], []);
    }

    // -------------------------------------------------------------------------
    // A2a — existing FK passes
    // -------------------------------------------------------------------------

    public function test_existing_fk_passes(): void
    {
        $record = RelationTargetModel::create(['title' => 'Hello']);

        // Must not throw
        $this->wrapper->validate(['target_id' => (int) $record->getKey()], []);

        $this->assertDatabaseHas('relation_targets', ['title' => 'Hello']);
    }

    // -------------------------------------------------------------------------
    // A2b — required empty value → 422
    // -------------------------------------------------------------------------

    public function test_required_empty_fk_throws_validation_exception(): void
    {
        $this->expectException(ValidationException::class);

        $this->wrapper->validate(['target_id' => ''], []);
    }

    // -------------------------------------------------------------------------
    // A2c — nullable absent FK passes (not required)
    // -------------------------------------------------------------------------

    public function test_nullable_absent_fk_passes(): void
    {
        // nullable field without required; omitted from payload → passes
        $this->wrapperNullable->validate([], []);

        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // A2d — nullable null value passes
    // -------------------------------------------------------------------------

    public function test_nullable_null_fk_passes(): void
    {
        $this->wrapperNullable->validate(['target_id' => null], []);

        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeWrapper(bool $required): LocaleAwareValidationWrapper
    {
        $definition = new class ($required) extends EntityDefinition {
            public string $model = RelationTargetModel::class;

            public function __construct(private readonly bool $isRequired) {}

            public function resolveName(): string
            {
                return 'relation_target';
            }

            public function fields(): array
            {
                $field = BelongsToField::make('target_id', RelationTargetModel::class);

                if ($this->isRequired) {
                    $field->required();
                }

                return [$field];
            }
        };

        /** @var ValidationFactory $factory */
        $factory = $this->app->make(ValidationFactory::class);
        $inner   = new LaravelValidationProvider($factory);

        // Configure locales via config so ConfigLocaleProvider resolves correctly.
        config()->set('bpadmin.locales', ['en']);
        $localeProvider = new ConfigLocaleProvider();

        return new LocaleAwareValidationWrapper($inner, $localeProvider, $definition);
    }
}

// ---------------------------------------------------------------------------
// Inline model fixture
// ---------------------------------------------------------------------------

use Illuminate\Database\Eloquent\Model;

/**
 * Minimal model pointing at the `relation_targets` table.
 * Used as the FK target in RelationExistsRewriteTest.
 */
class RelationTargetModel extends Model
{
    protected $table    = 'relation_targets';
    protected $fillable = ['title'];
}
