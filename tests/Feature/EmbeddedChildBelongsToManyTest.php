<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Feature;

use ArrayObject;
use BlackParadise\CoreAdmin\Domain\Contracts\Auth\AuthorizationProviderContract;
use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Fields\FieldContract;
use BlackParadise\CoreAdmin\Domain\Contracts\ValueHasherContract;
use BlackParadise\CoreAdmin\Domain\Fields\BelongsToManyField;
use BlackParadise\CoreAdmin\Domain\Fields\HasManyField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\FieldPersisters\FieldPersisterContract;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\FieldPersisters\FieldPersisterRegistry;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\FieldPersisters\PersistContext;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

/** @property int $id */
final class EcbtmShelf extends Model
{
    protected $table = 'ecbtm_shelves';
    protected $guarded = [];

    /** @return HasMany<EcbtmItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(EcbtmItem::class, 'ecbtm_shelf_id');
    }
}

/** @property int $id */
final class EcbtmItem extends Model
{
    protected $table = 'ecbtm_items';
    protected $guarded = [];

    /** @return BelongsToMany<EcbtmLabel, $this> */
    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(EcbtmLabel::class, 'ecbtm_item_label', 'ecbtm_item_id', 'ecbtm_label_id');
    }
}

/** @property int $id */
final class EcbtmLabel extends Model
{
    protected $table = 'ecbtm_labels';
    protected $guarded = [];
}

final class EcbtmItemDefinition extends EntityDefinition
{
    public string $model = EcbtmItem::class;

    public function resolveName(): string
    {
        return 'ecbtm_item';
    }

    public function fields(): array
    {
        return [
            TextField::make('title')->required(),
            BelongsToManyField::make('labels', EcbtmLabel::class),
        ];
    }
}

final class EcbtmShelfDefinition extends EntityDefinition
{
    public string $model = EcbtmShelf::class;

    public function resolveName(): string
    {
        return 'ecbtm_shelf';
    }

    public function fields(): array
    {
        return [
            TextField::make('name')->required(),
            HasManyField::make('items', EcbtmItem::class)->embed(EcbtmItemDefinition::class),
        ];
    }
}

final class EcbtmShelfMergeDefinition extends EntityDefinition
{
    public string $model = EcbtmShelf::class;

    public function resolveName(): string
    {
        return 'ecbtm_shelf';
    }

    public function fields(): array
    {
        return [
            TextField::make('name')->required(),
            HasManyField::make('items', EcbtmItem::class)->embed(EcbtmItemDefinition::class)->strategy('merge'),
        ];
    }
}

final class EcbtmShelfAppendDefinition extends EntityDefinition
{
    public string $model = EcbtmShelf::class;

    public function resolveName(): string
    {
        return 'ecbtm_shelf';
    }

    public function fields(): array
    {
        return [
            TextField::make('name')->required(),
            HasManyField::make('items', EcbtmItem::class)->embed(EcbtmItemDefinition::class)->strategy('append'),
        ];
    }
}


/**
 * Embedded hasMany child whose definition carries a belongsToMany field:
 * the pivot must be synced after the child row is saved (previously HTTP 500).
 */
final class EmbeddedChildBelongsToManyTest extends TestCase
{
    use RefreshDatabase;

    /** @var ArrayObject<int, EntityDefinitionContract> */
    private ArrayObject $seenDefinitions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seenDefinitions = new ArrayObject();
        $app = app();
        $app->bind(ValueHasherContract::class, static fn(): ValueHasherContract => new class implements ValueHasherContract {
            public function hash(string $value): string
            {
                return 'stub_hashed_' . $value;
            }

            public function isHashed(string $value): bool
            {
                return str_starts_with($value, 'stub_hashed_');
            }
        });

        Schema::create('users', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password');
            $t->rememberToken();
            $t->timestamps();
        });
        Schema::create('ecbtm_shelves', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->timestamps();
        });
        Schema::create('ecbtm_items', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('ecbtm_shelf_id');
            $t->string('title');
            $t->timestamps();
        });
        Schema::create('ecbtm_labels', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->timestamps();
        });
        Schema::create('ecbtm_item_label', function (Blueprint $t): void {
            $t->unsignedBigInteger('ecbtm_item_id');
            $t->unsignedBigInteger('ecbtm_label_id');
        });

        $registry = $app->make(EntityDefinitionRegistry::class);
        $registry->register(new EcbtmShelfDefinition());
        $registry->register(new EcbtmItemDefinition());

        $app->bind(AuthorizationProviderContract::class, static fn(): AuthorizationProviderContract => new class implements AuthorizationProviderContract {
            public function can(string $action, EntityDefinitionContract $entityDefinition): bool
            {
                return true;
            }
        });

        $user = new User();
        $user->forceFill(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'x'])->save();
        $this->actingAs($user);
    }

    /** @return list<int> */
    private function labelIds(string $itemTitle): array
    {
        $item = EcbtmItem::where('title', $itemTitle)->firstOrFail();

        $ids = [];
        foreach ($item->labels()->get() as $label) {
            $ids[] = $label->id;
        }

        return $ids;
    }

    /** @param array<string, mixed> $payload */
    private function store(array $payload): void
    {
        $this->postJson(route('bpadmin.entity.store', ['entity' => 'ecbtm_shelf']), $payload)->assertCreated();
    }

    public function test_create_host_syncs_child_belongs_to_many_pivot(): void
    {
        $a = EcbtmLabel::create(['name' => 'A']);
        $b = EcbtmLabel::create(['name' => 'B']);

        $this->store([
            'name'  => 'Shelf',
            'items' => [
                ['title' => 'One', 'labels' => [$a->id, $b->id]],
                ['title' => 'Two', 'labels' => [$b->id]],
            ],
        ]);

        $this->assertDatabaseCount('ecbtm_items', 2);
        $one = $this->labelIds('One');
        sort($one);
        self::assertSame([$a->id, $b->id], $one);
        self::assertSame([$b->id], $this->labelIds('Two'));
    }

    public function test_pivot_sync_context_carries_the_child_definition(): void
    {
        $registry = resolve(FieldPersisterRegistry::class);
        $real     = $registry->for(BelongsToManyField::make('labels', EcbtmLabel::class));
        $spy      = new class ($real, $this->seenDefinitions) implements FieldPersisterContract {
            /** @param ArrayObject<int, EntityDefinitionContract> $seen */
            public function __construct(private readonly FieldPersisterContract $inner, private readonly ArrayObject $seen) {}

            public function handles(FieldContract $field, array $attributes): bool
            {
                return $this->inner->handles($field, $attributes);
            }

            public function toAttributes(FieldContract $field, mixed $value, PersistContext $context): array
            {
                return $this->inner->toAttributes($field, $value, $context);
            }

            public function afterSave(FieldContract $field, mixed $value, Model $host, PersistContext $context): void
            {
                if ($host instanceof EcbtmItem) {
                    $this->seen->append($context->definition);
                }
                $this->inner->afterSave($field, $value, $host, $context);
            }

            public function beforeDelete(FieldContract $field, Model $host, PersistContext $context): void
            {
                $this->inner->beforeDelete($field, $host, $context);
            }
        };
        $registry->register('belongs_to_many', $spy);

        $a = EcbtmLabel::create(['name' => 'A']);
        $this->store(['name' => 'Shelf', 'items' => [['title' => 'One', 'labels' => [$a->id]]]]);

        self::assertCount(1, $this->seenDefinitions);
        foreach ($this->seenDefinitions as $definition) {
            self::assertInstanceOf(EcbtmItemDefinition::class, $definition);
        }
    }

    public function test_merge_strategy_resyncs_pivot_of_existing_child_and_keeps_it_when_key_absent(): void
    {
        $a = EcbtmLabel::create(['name' => 'A']);
        $b = EcbtmLabel::create(['name' => 'B']);
        $shelf = EcbtmShelf::create(['name' => 'S']);
        $item  = $shelf->items()->create(['title' => 'One']);
        $item->labels()->sync([$a->id]);
        $other = $shelf->items()->create(['title' => 'Two']);
        $other->labels()->sync([$a->id]);

        $this->putMergeDefinition();

        $this->putJson(route('bpadmin.entity.update', ['entity' => 'ecbtm_shelf', 'id' => $shelf->id]), [
            'name'  => 'S',
            'items' => [
                ['id' => $item->id, 'title' => 'One', 'labels' => [$b->id]],
                ['id' => $other->id, 'title' => 'Two'],
            ],
        ])->assertSuccessful();

        self::assertSame([$b->id], $this->labelIds('One'));
        self::assertSame([$a->id], $this->labelIds('Two'));
    }

    public function test_append_strategy_syncs_pivot_of_new_children(): void
    {
        $a = EcbtmLabel::create(['name' => 'A']);
        $shelf = EcbtmShelf::create(['name' => 'S']);
        $existing = $shelf->items()->create(['title' => 'Old']);

        $this->putAppendDefinition();

        $this->putJson(route('bpadmin.entity.update', ['entity' => 'ecbtm_shelf', 'id' => $shelf->id]), [
            'name'  => 'S',
            'items' => [['title' => 'New', 'labels' => [$a->id]]],
        ])->assertSuccessful();

        self::assertSame([$a->id], $this->labelIds('New'));
        self::assertSame([], $this->labelIds('Old'));
        self::assertDatabaseHas('ecbtm_items', ['id' => $existing->id]);
    }

    private function putMergeDefinition(): void
    {
        resolve(EntityDefinitionRegistry::class)->register(new EcbtmShelfMergeDefinition());
    }

    private function putAppendDefinition(): void
    {
        resolve(EntityDefinitionRegistry::class)->register(new EcbtmShelfAppendDefinition());
    }
}
