<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Persistence;

use BlackParadise\CoreAdmin\Domain\Mutators\EntityMutatorInterface;
use BlackParadise\CoreAdmin\Domain\Repositories\EntityRepositoryInterface;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\ActsAsPermissiveAdmin;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ModelLess\ArrayMutator;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ModelLess\ArrayRepository;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ModelLess\ArrayStore;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ModelLess\CustomReadItems;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ModelLess\Reports;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItem;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItemDefinition;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;

/**
 * A32: a definition with a custom repository() reads from it while writes still go through
 * Eloquent; every other definition keeps using the globally bound Eloquent repository/mutator.
 *
 * Contract for the impl (Task 4.5): class `BlackParadise\LaravelAdmin\Core\EntityPersistenceResolver`,
 * container-resolvable, with `repositoryFor(EntityDefinitionContract): EntityRepositoryInterface` and
 * `mutatorFor(EntityDefinitionContract): EntityMutatorInterface`.
 */
final class EntityPersistenceResolverTest extends TestCase
{
    use ActsAsPermissiveAdmin;

    private const RESOLVER = 'BlackParadise\\LaravelAdmin\\Core\\EntityPersistenceResolver';

    protected function setUp(): void
    {
        parent::setUp();
        $this->stubHasher();
        $this->permitEverything();

        Schema::create('test_items', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('email')->nullable();
            $t->timestamps();
        });
        $this->loginAdmin();

        ArrayStore::reset();
        $registry = app()->make(EntityDefinitionRegistry::class);
        $registry->register(new TestItemDefinition());
        $registry->register(new CustomReadItems());
    }

    protected function tearDown(): void
    {
        ArrayStore::reset();
        parent::tearDown();
    }

    private function resolver(): object
    {
        self::assertTrue(class_exists(self::RESOLVER), 'EntityPersistenceResolver must exist in BlackParadise\LaravelAdmin\Core.');

        return app()->make(self::RESOLVER);
    }

    public function test_resolver_class_exists_and_is_container_resolvable(): void
    {
        $resolver = $this->resolver();

        self::assertSame(self::RESOLVER, $resolver::class);
    }

    public function test_repository_for_definition_with_custom_repository_returns_that_repository(): void
    {
        $resolver = $this->resolver();

        $repository = (new ReflectionMethod($resolver, 'repositoryFor'))->invoke($resolver, new Reports());

        self::assertInstanceOf(ArrayRepository::class, $repository);
    }

    public function test_repository_for_definition_without_custom_repository_returns_the_global_binding(): void
    {
        $resolver = $this->resolver();

        $repository = (new ReflectionMethod($resolver, 'repositoryFor'))->invoke($resolver, new TestItemDefinition());

        self::assertInstanceOf(app()->make(EntityRepositoryInterface::class)::class, $repository);
    }

    public function test_mutator_for_definition_with_custom_mutator_returns_that_mutator(): void
    {
        $resolver = $this->resolver();

        $mutator = (new ReflectionMethod($resolver, 'mutatorFor'))->invoke($resolver, new Reports());

        self::assertInstanceOf(ArrayMutator::class, $mutator);
    }

    public function test_mutator_for_definition_with_only_a_custom_repository_returns_the_global_eloquent_mutator(): void
    {
        $resolver = $this->resolver();

        $mutator = (new ReflectionMethod($resolver, 'mutatorFor'))->invoke($resolver, new CustomReadItems());

        self::assertInstanceOf(app()->make(EntityMutatorInterface::class)::class, $mutator);
    }

    public function test_index_lists_records_from_the_custom_repository_not_from_the_table(): void
    {
        ArrayStore::reset([1 => ['id' => 1, 'name' => 'FromRepo', 'email' => 'repo@example.com']]);
        TestItem::create(['name' => 'FromDb', 'email' => 'db@example.com']);

        $response = $this->getJson(route('bpadmin.entity.index', ['entity' => 'custom_read_items']));

        $response->assertOk();
        self::assertSame(['FromRepo'], array_column((array) $response->json('data'), 'name'));
    }

    public function test_show_finds_the_record_from_the_custom_repository(): void
    {
        ArrayStore::reset([7 => ['id' => 7, 'name' => 'Only In Repo', 'email' => 'repo@example.com']]);

        $response = $this->getJson(route('bpadmin.entity.show', ['entity' => 'custom_read_items', 'id' => 7]));

        $response->assertOk()->assertJsonPath('data.name', 'Only In Repo');
    }

    public function test_store_still_writes_through_eloquent_when_only_the_repository_is_custom(): void
    {
        $this->postJson(
            route('bpadmin.entity.store', ['entity' => 'custom_read_items']),
            ['name' => 'Written', 'email' => 'w@example.com'],
        );

        $this->assertDatabaseHas('test_items', ['name' => 'Written']);
        self::assertSame([], ArrayStore::$rows);
    }

    public function test_definition_without_custom_repository_keeps_reading_from_eloquent(): void
    {
        ArrayStore::reset([1 => ['id' => 1, 'name' => 'FromRepo', 'email' => 'repo@example.com']]);
        TestItem::create(['name' => 'FromDb', 'email' => 'db@example.com']);

        $response = $this->getJson(route('bpadmin.entity.index', ['entity' => 'test_item']));

        $response->assertOk();
        self::assertSame(['FromDb'], array_column((array) $response->json('data'), 'name'));
    }
}
