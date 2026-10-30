<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Persistence;

use BlackParadise\CoreAdmin\Domain\Entity\EntityRecord;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\CoreAdmin\Domain\Mutators\EntityMutatorInterface;
use BlackParadise\CoreAdmin\Domain\ValueObjects\EntityKey;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\RelationWriter;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\ActsAsPermissiveAdmin;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\CustomPersister\SlugField;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItem;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Task 2.1 — A19 (host registers a persister for a custom field type; its values are
 * written via it on save) + U-08 (RelationWriter removed).
 */
final class FieldPersisterRegistryTest extends TestCase
{
    use RefreshDatabase;
    use ActsAsPermissiveAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stubHasher();
        Schema::create('test_items', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('email')->nullable();
            $t->string('password')->nullable();
            $t->unsignedBigInteger('category_id')->nullable();
            $t->timestamps();
        });
    }

    private function slugDefinition(): EntityDefinition
    {
        return new class extends EntityDefinition {
            public string $model = TestItem::class;

            public function resolveName(): string
            {
                return 'slug_item';
            }

            public function fields(): array
            {
                return [TextField::make('name'), SlugField::make('email')];
            }
        };
    }

    private const NS = 'BlackParadise\\LaravelAdmin\\Infrastructure\\Persistence\\FieldPersisters\\';

    /**
     * Class names are strings (and the host persister is eval'd) because the persister
     * classes do not exist before Task 2.2 — tests must neither fatal nor trip static analysis.
     */
    private function requireRegistry(): mixed
    {
        self::assertTrue(class_exists(self::NS . 'FieldPersisterRegistry'), 'FieldPersisterRegistry must exist');

        return app()->make(self::NS . 'FieldPersisterRegistry');
    }

    /** Host persister: stores the value slugified (`Hello World` -> `hello-world`). */
    private function slugPersister(): mixed
    {
        self::assertTrue(class_exists(self::NS . 'AbstractFieldPersister'), 'AbstractFieldPersister must exist');
        $class = 'BlackParadise\\LaravelAdmin\\Tests\\Runtime\\SlugPersister';
        if (!class_exists($class, false)) {
            eval(<<<'PHP'
                namespace BlackParadise\LaravelAdmin\Tests\Runtime;

                final class SlugPersister extends \BlackParadise\LaravelAdmin\Infrastructure\Persistence\FieldPersisters\AbstractFieldPersister
                {
                    public function toAttributes(\BlackParadise\CoreAdmin\Domain\Contracts\Fields\FieldContract $field, mixed $value, \BlackParadise\LaravelAdmin\Infrastructure\Persistence\FieldPersisters\PersistContext $context): array
                    {
                        return [$field->name() => \Illuminate\Support\Str::slug((string) $value)];
                    }
                }
                PHP);
        }

        return app()->make($class);
    }

    public function test_registry_is_a_shared_container_singleton(): void
    {
        $registry = $this->requireRegistry();

        self::assertSame($registry, $this->requireRegistry());
    }

    public function test_unknown_custom_field_type_falls_back_to_column_persister(): void
    {
        $registry = $this->requireRegistry();

        self::assertSame(self::NS . 'ColumnPersister', get_debug_type($registry->for(SlugField::make('email'))));
    }

    public function test_registered_custom_persister_is_resolved_for_its_type(): void
    {
        $registry = $this->requireRegistry();
        $persister = $this->slugPersister();

        $registry->register('slug', $persister);

        self::assertSame($persister, $registry->for(SlugField::make('email')));
    }

    public function test_custom_type_value_is_written_via_registered_persister_on_create(): void
    {
        $this->requireRegistry()->register('slug', $this->slugPersister());

        $created = app()->make(EntityMutatorInterface::class)
            ->create(new EntityRecord($this->slugDefinition(), ['name' => 'N', 'email' => 'Hello World']));

        self::assertSame('hello-world', DB::table('test_items')->where('id', $created->id())->value('email'));
    }

    public function test_custom_type_value_is_written_via_registered_persister_on_update(): void
    {
        $this->requireRegistry()->register('slug', $this->slugPersister());
        $mutator = app()->make(EntityMutatorInterface::class);
        $created = $mutator->create(new EntityRecord($this->slugDefinition(), ['name' => 'N', 'email' => 'a']));

        $mutator->update(new EntityKey($created->id()), new EntityRecord($this->slugDefinition(), ['email' => 'Brand New Slug']));

        self::assertSame('brand-new-slug', DB::table('test_items')->where('id', $created->id())->value('email'));
    }

    public function test_relation_writer_is_removed_in_favour_of_persisters(): void
    {
        self::assertFalse(class_exists(RelationWriter::class), 'RelationWriter must be removed (U-08)');
    }
}
