<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Persistence;

use BlackParadise\CoreAdmin\Domain\Contracts\Entity\EntityRecordContract;
use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\Fields\BelongsToField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\CoreAdmin\Domain\Mutators\EntityMutatorInterface;
use BlackParadise\CoreAdmin\Domain\ValueObjects\EntityKey;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\ActsAsPermissiveAdmin;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestAuthor;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestAuthorDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestPost;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

final class DecoratedPostDefinition extends EntityDefinition
{
    public string $model = TestPost::class;

    public function resolveName(): string
    {
        return 'decorated_post';
    }

    public function fields(): array
    {
        return [
            TextField::make('title'),
            BelongsToField::make('author_id', TestAuthor::class)->embed(TestAuthorDefinition::class),
        ];
    }
}

/** Records every call (WRP-style decorator) and delegates to the real mutator. */
final class RecordingMutator implements EntityMutatorInterface
{
    /** @var list<string> */
    public static array $calls = [];

    public function __construct(private readonly EntityMutatorInterface $inner) {}

    public function create(EntityRecordContract $record): EntityRecordContract
    {
        self::$calls[] = 'create:' . $record->definition()->name();

        return $this->inner->create($record);
    }

    public function update(EntityKey $key, EntityRecordContract $record): EntityRecordContract
    {
        self::$calls[] = 'update:' . $record->definition()->name();

        return $this->inner->update($key, $record);
    }

    public function delete(EntityKey $key, EntityDefinitionContract $entityDefinition): bool
    {
        self::$calls[] = 'delete:' . $entityDefinition->name();

        return $this->inner->delete($key, $entityDefinition);
    }
}

/** Task 2.1 — A21: decoration of EntityMutatorInterface still sees host AND embedded child writes. */
final class DecoratedMutatorTest extends TestCase
{
    use RefreshDatabase;
    use ActsAsPermissiveAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        RecordingMutator::$calls = [];
        $this->stubHasher();
        Schema::create('test_authors', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('email');
            $t->timestamps();
        });
        Schema::create('test_posts', function (Blueprint $t): void {
            $t->id();
            $t->string('title');
            $t->unsignedBigInteger('author_id')->nullable();
            $t->timestamps();
        });
        $this->permitEverything();
        app()->extend(EntityMutatorInterface::class, fn(EntityMutatorInterface $inner): EntityMutatorInterface => new RecordingMutator($inner));

        $registry = app()->make(EntityDefinitionRegistry::class);
        $registry->register(new DecoratedPostDefinition());
        $registry->register(new TestAuthorDefinition());
        $this->loginAdmin();
    }

    public function test_store_with_embedded_child_goes_through_decorator_for_host_and_child(): void
    {
        $this->postJson(route('bpadmin.entity.store', ['entity' => 'decorated_post']), [
            'title' => 'P',
            'author_id' => ['name' => 'Ann', 'email' => 'ann@e.com'],
        ])->assertCreated();

        self::assertEqualsCanonicalizing(['create:decorated_post', 'create:test_author'], RecordingMutator::$calls);
        $this->assertDatabaseHas('test_posts', ['title' => 'P']);
        $this->assertDatabaseHas('test_authors', ['name' => 'Ann']);
    }

    public function test_update_with_embedded_child_goes_through_decorator_for_host_and_child(): void
    {
        $author = TestAuthor::create(['name' => 'Ann', 'email' => 'ann@e.com']);
        $post = TestPost::create(['title' => 'P', 'author_id' => $author->id]);

        $this->putJson(route('bpadmin.entity.update', ['entity' => 'decorated_post', 'id' => $post->id]), [
            'title' => 'P2',
            'author_id' => ['name' => 'Anna', 'email' => 'ann@e.com'],
        ])->assertOk();

        self::assertEqualsCanonicalizing(['update:decorated_post', 'update:test_author'], RecordingMutator::$calls);
        $this->assertDatabaseHas('test_authors', ['id' => $author->id, 'name' => 'Anna']);
    }

    public function test_decorated_mutator_is_the_one_resolved_from_the_container(): void
    {
        self::assertInstanceOf(RecordingMutator::class, app()->make(EntityMutatorInterface::class));
    }
}
