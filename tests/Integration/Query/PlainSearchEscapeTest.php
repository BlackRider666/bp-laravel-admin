<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Query;

use BlackParadise\CoreAdmin\Domain\Contracts\Entity\EntityRecordContract;
use BlackParadise\CoreAdmin\Domain\Query\Criteria;
use BlackParadise\CoreAdmin\Domain\Repositories\EntityRepositoryInterface;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItem;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItemFilterableDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Query\Concerns\BuildsSearchableArticles;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

/**
 * Task 2.3 — A4 (plain searchable fields): `%`, `_` and `\` in the term match literally.
 */
final class PlainSearchEscapeTest extends TestCase
{
    use BuildsSearchableArticles;

    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stubQueryHasher();
        Schema::create('test_items', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->timestamps();
        });
        TestItem::create(['name' => 'Alice', 'email' => 'alice@example.com']);
        TestItem::create(['name' => '100%_real', 'email' => 'pct@example.com']);
        TestItem::create(['name' => 'back\\slash', 'email' => 'bs@example.com']);
    }

    /** @return list<mixed> */
    private function search(string $term): array
    {
        $result = app()->make(EntityRepositoryInterface::class)
            ->list(new TestItemFilterableDefinition(), new Criteria(search: $term));

        return array_values(array_map(fn(EntityRecordContract $r): mixed => $r->get('name'), $result->items));
    }

    public function test_percent_matches_only_the_row_containing_a_literal_percent(): void
    {
        self::assertSame(['100%_real'], $this->search('%'));
    }

    public function test_underscore_matches_only_the_row_containing_a_literal_underscore(): void
    {
        self::assertSame(['100%_real'], $this->search('_'));
    }

    public function test_backslash_matches_only_the_row_containing_a_literal_backslash(): void
    {
        self::assertSame(['back\\slash'], $this->search('\\'));
    }

    public function test_percent_inside_a_term_is_not_a_wildcard(): void
    {
        self::assertSame([], $this->search('Al%ce'));
    }

    public function test_underscore_inside_a_term_is_not_a_single_char_wildcard(): void
    {
        self::assertSame([], $this->search('Al_ce'));
    }

    public function test_plain_substring_search_still_works(): void
    {
        self::assertSame(['Alice'], $this->search('lic'));
    }
}
