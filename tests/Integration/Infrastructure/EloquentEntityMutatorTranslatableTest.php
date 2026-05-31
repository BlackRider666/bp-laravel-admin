<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Infrastructure;

use BlackParadise\CoreAdmin\Domain\Contracts\Auth\AuthorizationProviderContract;
use BlackParadise\CoreAdmin\Domain\Entity\EntityRecord;
use BlackParadise\CoreAdmin\Domain\Fields\TranslatableField;
use BlackParadise\CoreAdmin\Domain\Mutators\EntityMutatorInterface;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestArticle;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestArticleDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestArticleWithAsArrayObject;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use DB;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Mockery;

final class EloquentEntityMutatorTranslatableTest extends TestCase
{
    use RefreshDatabase;

    private EntityMutatorInterface $mutator;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('test_articles', function (Blueprint $table): void {
            $table->id();
            $table->json('title')->nullable();
            $table->timestamps();
        });

        Schema::create('test_articles_as_array_object', function (Blueprint $table): void {
            $table->id();
            $table->json('title')->nullable();
            $table->timestamps();
        });

        Schema::create('test_articles_as_collection', function (Blueprint $table): void {
            $table->id();
            $table->json('title')->nullable();
            $table->timestamps();
        });

        $this->app->bind(AuthorizationProviderContract::class, function (): AuthorizationProviderContract {
            $mock = Mockery::mock(AuthorizationProviderContract::class);
            $mock->shouldReceive('can')->andReturn(true);
            return $mock;
        });

        $this->mutator = $this->app->make(EntityMutatorInterface::class);
    }

    public function test_model_with_array_cast_does_not_get_double_encoded(): void
    {
        $definition = new TestArticleDefinition();
        $record     = new EntityRecord($definition, [
            'title' => ['en' => 'Hello', 'uk' => 'Привіт'],
        ]);

        $created = $this->mutator->create($record);

        $article = TestArticle::find($created->id());

        // Eloquent decodes 'array' cast → PHP array. If double-encoded, this
        // would be a JSON string like '"{\"en\":\"Hello\"...}"'.
        $this->assertIsArray($article->title);
        $this->assertSame('Hello', $article->title['en']);
        $this->assertSame('Привіт', $article->title['uk']);
    }

    public function test_raw_db_value_is_valid_single_encoded_json(): void
    {
        $definition = new TestArticleDefinition();
        $this->mutator->create(new EntityRecord($definition, [
            'title' => ['en' => 'Hi'],
        ]));

        $raw = DB::table('test_articles')->first()->title;
        $this->assertIsString($raw);

        $decoded = json_decode($raw, true);
        $this->assertSame(['en' => 'Hi'], $decoded);  // single decode → array (not another json string)
    }

    public function test_managed_by_model_flag_skips_mutator_encoding(): void
    {
        $definition = new class extends EntityDefinition {
            public string $model = TestArticle::class;
            public function resolveName(): string
            {
                return 'test_article';
            }
            public function fields(): array
            {
                return [TranslatableField::make('title')->managedByModel()];
            }
        };

        $record = new EntityRecord($definition, [
            'title' => ['en' => 'Flag', 'uk' => 'Флаг'],
        ]);

        $created = $this->mutator->create($record);

        $article = TestArticle::find($created->id());
        $this->assertIsArray($article->title);
        $this->assertSame('Flag', $article->title['en']);
    }

    public function test_model_without_cast_still_gets_encoded(): void
    {
        // Модель без cast: mutator має зробити json_encode, інакше буде
        // array→string conversion error при SQL insert.
        Schema::create('test_plain_translatables', function (Blueprint $table): void {
            $table->id();
            $table->text('title')->nullable();
            $table->timestamps();
        });

        $plainModel = new class extends Model {
            protected $table = 'test_plain_translatables';
            protected $guarded = [];
            // NO $casts — deliberate.
        };
        $plainClass = $plainModel::class;

        $definition = new class ($plainClass) extends EntityDefinition {
            public function __construct(public string $model) {}
            public function resolveName(): string
            {
                return 'test_plain_translatable';
            }
            public function fields(): array
            {
                return [TranslatableField::make('title')];
            }
        };

        $created = $this->mutator->create(new EntityRecord($definition, [
            'title' => ['en' => 'Plain'],
        ]));

        $row = DB::table('test_plain_translatables')->find($created->id());
        $this->assertIsString($row->title);
        $this->assertSame(['en' => 'Plain'], json_decode($row->title, true));
    }

    public function test_translatable_field_is_not_double_encoded_with_as_array_object_cast(): void
    {
        $definition = new class extends EntityDefinition {
            public string $model = TestArticleWithAsArrayObject::class;
            public function resolveName(): string
            {
                return 'test_article_as_array_object';
            }
            public function fields(): array
            {
                return [TranslatableField::make('title')];
            }
        };

        $created = $this->mutator->create(new EntityRecord($definition, [
            'title' => ['en' => 'Hello', 'uk' => 'Привіт'],
        ]));

        // Raw DB column must be valid single-encoded JSON (not a JSON string of a JSON string).
        $raw = DB::table('test_articles_as_array_object')->find($created->id())->title;
        $this->assertIsString($raw);
        $decoded = json_decode($raw, true);
        $this->assertSame(['en' => 'Hello', 'uk' => 'Привіт'], $decoded);

        // Via model cast — AsArrayObject; getArrayCopy() must return the locale map.
        $article = TestArticleWithAsArrayObject::find($created->id());
        $this->assertSame(['en' => 'Hello', 'uk' => 'Привіт'], $article->title->getArrayCopy());
    }

    public function test_translatable_field_is_not_double_encoded_with_as_collection_cast(): void
    {
        // Inline anonymous model with AsCollection cast.
        $asCollectionModel = new class extends Model {
            protected $table = 'test_articles_as_collection';
            protected $guarded = [];
            protected $casts = [
                'title' => AsCollection::class,
            ];
        };
        $asCollectionClass = $asCollectionModel::class;

        $definition = new class ($asCollectionClass) extends EntityDefinition {
            public function __construct(public string $model) {}
            public function resolveName(): string
            {
                return 'test_article_as_collection';
            }
            public function fields(): array
            {
                return [TranslatableField::make('title')];
            }
        };

        $created = $this->mutator->create(new EntityRecord($definition, [
            'title' => ['en' => 'World', 'uk' => 'Світ'],
        ]));

        // Raw DB column must be valid single-encoded JSON.
        $raw = DB::table('test_articles_as_collection')->find($created->id())->title;
        $this->assertIsString($raw);
        $decoded = json_decode($raw, true);
        $this->assertSame(['en' => 'World', 'uk' => 'Світ'], $decoded);

        // Via model cast — AsCollection; toArray() must return the locale map.
        $row = $asCollectionModel->newQuery()->find($created->id());
        $this->assertSame(['en' => 'World', 'uk' => 'Світ'], $row->title->toArray());
    }
}
