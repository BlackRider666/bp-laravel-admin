<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Query\Concerns;

use BlackParadise\CoreAdmin\Domain\Contracts\Entity\EntityRecordContract;
use BlackParadise\CoreAdmin\Domain\Contracts\ValueHasherContract;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\CoreAdmin\Domain\Fields\TranslatableField;
use BlackParadise\CoreAdmin\Domain\Query\Criteria;
use BlackParadise\CoreAdmin\Domain\Query\PaginatedResult;
use BlackParadise\CoreAdmin\Domain\Repositories\EntityRepositoryInterface;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestArticle;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shared fixtures for the Task 2.3 query tests: a `test_articles` table with a translatable
 * `title` (TEXT, so legacy non-JSON rows can be stored) and a plain `slug`.
 */
trait BuildsSearchableArticles
{
    protected function stubQueryHasher(): void
    {
        app()->bind(ValueHasherContract::class, fn(): ValueHasherContract => new class implements ValueHasherContract {
            public function hash(string $value): string
            {
                return 'stub_hashed_' . $value;
            }

            public function isHashed(string $value): bool
            {
                return str_starts_with($value, 'stub_hashed_');
            }
        });
    }

    protected function createArticlesTable(): void
    {
        Schema::dropIfExists('test_articles');
        Schema::create('test_articles', function (Blueprint $table): void {
            $table->id();
            $table->text('title')->nullable();
            $table->string('slug')->nullable();
            $table->timestamps();
        });
    }

    /**
     * @param array<string, string>|string $title array => stored as JSON (as the mutator does); string => stored verbatim (legacy row)
     */
    protected function insertArticle(array|string $title, ?string $slug = null): int
    {
        return DB::table('test_articles')->insertGetId([
            'title' => is_array($title) ? json_encode($title, JSON_UNESCAPED_UNICODE) : $title,
            'slug' => $slug,
        ]);
    }

    /** Translatable `title` only (searchable + sortable). */
    protected function titleOnlyDefinition(): EntityDefinition
    {
        return new class extends EntityDefinition {
            public string $model = TestArticle::class;

            public function resolveName(): string
            {
                return 'article_title_only';
            }

            public function fields(): array
            {
                return [TranslatableField::make('title')->sortable()];
            }

            public function searchFields(): array
            {
                return ['title'];
            }
        };
    }

    /** One translatable + one plain searchable field. */
    protected function titleAndSlugDefinition(): EntityDefinition
    {
        return new class extends EntityDefinition {
            public string $model = TestArticle::class;

            public function resolveName(): string
            {
                return 'article_title_slug';
            }

            public function fields(): array
            {
                return [TranslatableField::make('title'), TextField::make('slug')];
            }

            public function searchFields(): array
            {
                return ['title', 'slug'];
            }
        };
    }

    protected function repository(): EntityRepositoryInterface
    {
        app()->forgetInstance(EntityRepositoryInterface::class);

        return app()->make(EntityRepositoryInterface::class);
    }

    /** @return list<mixed> record ids, sorted ascending (order-insensitive comparison) */
    protected function matchedIds(PaginatedResult $result): array
    {
        $ids = array_map(fn(EntityRecordContract $r): mixed => $r->id(), $result->items);
        sort($ids);

        return $ids;
    }

    /** @return list<mixed> */
    protected function searchIds(EntityDefinition $definition, string $term): array
    {
        return $this->matchedIds($this->repository()->list($definition, new Criteria(search: $term)));
    }
}
