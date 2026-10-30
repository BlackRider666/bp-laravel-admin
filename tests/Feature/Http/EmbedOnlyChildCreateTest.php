<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Feature\Http;

use BlackParadise\CoreAdmin\Domain\Fields\BelongsToField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\CoreAdmin\Domain\ValueObjects\Capability;
use BlackParadise\CoreAdmin\Domain\ValueObjects\EntityCapabilities;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\ActsAsPermissiveAdmin;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

final class EoPublication extends Model
{
    protected $table = 'eo_publications';
    protected $guarded = [];
}

final class EoPatent extends Model
{
    protected $table = 'eo_patents';
    protected $guarded = [];

    /** @return BelongsTo<EoPublication, $this> */
    public function publication(): BelongsTo
    {
        return $this->belongsTo(EoPublication::class, 'publication_id');
    }
}

/** Embed-only definition: registered solely as an embed target. */
final class EoPublicationDefinition extends EntityDefinition
{
    public bool $creatable = false;

    public string $model = EoPublication::class;

    public function capabilities(): EntityCapabilities
    {
        return EntityCapabilities::all()->without(Capability::Create)->without(Capability::Update);
    }

    public function resolveName(): string
    {
        return 'eo_publication';
    }

    public function fields(): array
    {
        return [TextField::make('title')->required()];
    }
}

final class EoPatentDefinition extends EntityDefinition
{
    public string $model = EoPatent::class;

    public function resolveName(): string
    {
        return 'eo_patent';
    }

    public function fields(): array
    {
        return [
            TextField::make('number')->required(),
            BelongsToField::make('publication_id', EoPublication::class)
                ->embed(EoPublicationDefinition::class),
        ];
    }
}

/**
 * Regression: `$creatable = false` governs the STANDALONE create surface only.
 * An embed-only definition must still be creatable as an embedded child of a
 * host (previously CapabilityDeniedException → 404 on the host store).
 */
final class EmbedOnlyChildCreateTest extends TestCase
{
    use RefreshDatabase;
    use ActsAsPermissiveAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('eo_publications', function (Blueprint $t): void {
            $t->id();
            $t->string('title');
            $t->timestamps();
        });
        Schema::create('eo_patents', function (Blueprint $t): void {
            $t->id();
            $t->string('number');
            $t->unsignedBigInteger('publication_id')->nullable();
            $t->timestamps();
        });

        /** @var EntityDefinitionRegistry $registry */
        $registry = resolve(EntityDefinitionRegistry::class);
        $registry->register(new EoPatentDefinition());
        $registry->register(new EoPublicationDefinition());

        $this->permitEverything();

        $this->loginAdmin();
    }

    public function test_host_create_with_embed_only_child_succeeds_and_persists_child(): void
    {
        $response = $this->postJson(
            route('bpadmin.entity.store', ['entity' => 'eo_patent']),
            ['number' => 'P-1', 'publication_id' => ['title' => 'Embedded pub']],
        );

        $response->assertCreated();
        $this->assertDatabaseHas('eo_publications', ['title' => 'Embedded pub']);
        $pub = EoPublication::query()->firstOrFail();
        $this->assertDatabaseHas('eo_patents', ['number' => 'P-1', 'publication_id' => $pub->getKey()]);
    }

    public function test_standalone_store_of_embed_only_definition_is_still_not_found(): void
    {
        $this->postJson(
            route('bpadmin.entity.store', ['entity' => 'eo_publication']),
            ['title' => 'Standalone'],
        )->assertNotFound();

        $this->assertDatabaseCount('eo_publications', 0);
    }

    public function test_host_update_with_embed_only_child_updates_child_but_standalone_update_is_denied(): void
    {
        $pub = EoPublication::query()->create(['title' => 'Old']);
        $patent = EoPatent::query()->create(['number' => 'P-2', 'publication_id' => $pub->getKey()]);

        $this->putJson(
            route('bpadmin.entity.update', ['entity' => 'eo_patent', 'id' => $patent->getKey()]),
            ['number' => 'P-2', 'publication_id' => ['title' => 'New']],
        )->assertSuccessful();
        $this->assertDatabaseHas('eo_publications', ['id' => $pub->getKey(), 'title' => 'New']);

        $this->putJson(
            route('bpadmin.entity.update', ['entity' => 'eo_publication', 'id' => $pub->getKey()]),
            ['title' => 'Standalone'],
        )->assertNotFound();
        $this->assertDatabaseHas('eo_publications', ['id' => $pub->getKey(), 'title' => 'New']);
    }
}
