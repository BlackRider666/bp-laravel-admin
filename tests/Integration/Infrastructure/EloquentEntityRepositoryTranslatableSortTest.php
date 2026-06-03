<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Infrastructure;

use BlackParadise\CoreAdmin\Domain\Contracts\Entity\EntityRecordContract;
use BlackParadise\CoreAdmin\Domain\Fields\TranslatableField;
use BlackParadise\CoreAdmin\Domain\Query\Criteria;
use BlackParadise\CoreAdmin\Domain\Query\Sort;
use BlackParadise\CoreAdmin\Domain\Repositories\EntityRepositoryInterface;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Integration tests for H1 + H2 — translatable-field sort must work on SQLite (CI)
 * and must use an available locale, not app.locale, to extract JSON values.
 *
 * H1: orderByRaw must be driver-aware (SQLite uses json_extract without JSON_UNQUOTE).
 * H2: locale selected for sorting must be first of availableLocales(), not app.locale,
 *     so sorting works even when content is stored only in a non-default locale.
 */
final class EloquentEntityRepositoryTranslatableSortTest extends TestCase
{
    use RefreshDatabase;

    private EntityRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('test_translatable_items', function (Blueprint $table): void {
            $table->id();
            $table->json('title');
            $table->timestamps();
        });

        $this->repository = $this->app->make(EntityRepositoryInterface::class);
    }

    // -------------------------------------------------------------------------
    // H1: SQLite-aware json_extract (no JSON_UNQUOTE)
    // -------------------------------------------------------------------------

    public function test_translatable_sort_asc_returns_correct_order_on_sqlite(): void
    {
        // Configure a single available locale so H2 logic picks 'uk'.
        config()->set('bpadmin.locales', ['uk']);
        config()->set('app.locale', 'uk');

        $this->insertRows([
            ['uk' => 'Яблуко'],
            ['uk' => 'Абрикос'],
            ['uk' => 'Вишня'],
        ]);

        $definition = $this->makeDefinition();

        $result = $this->repository->list(
            $definition,
            new Criteria(sort: [new Sort('title', 'asc')]),
        );

        $labels = array_map(fn(EntityRecordContract $item): mixed => json_decode((string) $item->get('title'), true)['uk'], $result->items);

        self::assertSame(['Абрикос', 'Вишня', 'Яблуко'], $labels);
    }

    public function test_translatable_sort_desc_returns_correct_order_on_sqlite(): void
    {
        config()->set('bpadmin.locales', ['uk']);
        config()->set('app.locale', 'uk');

        $this->insertRows([
            ['uk' => 'Яблуко'],
            ['uk' => 'Абрикос'],
            ['uk' => 'Вишня'],
        ]);

        $definition = $this->makeDefinition();

        $result = $this->repository->list(
            $definition,
            new Criteria(sort: [new Sort('title', 'desc')]),
        );

        $labels = array_map(fn(EntityRecordContract $item): mixed => json_decode((string) $item->get('title'), true)['uk'], $result->items);

        self::assertSame(['Яблуко', 'Вишня', 'Абрикос'], $labels);
    }

    // -------------------------------------------------------------------------
    // H2: sort locale is first of availableLocales(), not app.locale
    // -------------------------------------------------------------------------

    /**
     * Scenario: app.locale='en', but content is stored only in 'uk'.
     * Before the fix, JSON_EXTRACT($.en) would return NULL for all rows →
     * sort was silently broken. After the fix, sort picks 'uk' and works.
     */
    public function test_translatable_sort_works_when_content_locale_differs_from_app_locale(): void
    {
        // app.locale is 'en', but bpadmin.locales lists only 'uk'.
        config()->set('app.locale', 'en');
        config()->set('bpadmin.locales', ['uk']);

        $this->insertRows([
            ['uk' => 'Яблуко'],
            ['uk' => 'Абрикос'],
            ['uk' => 'Вишня'],
        ]);

        $definition = $this->makeDefinition();

        $result = $this->repository->list(
            $definition,
            new Criteria(sort: [new Sort('title', 'asc')]),
        );

        // Must not be all NULLs → order must be meaningful, not random.
        $labels = array_map(fn(EntityRecordContract $item): mixed => json_decode((string) $item->get('title'), true)['uk'], $result->items);

        self::assertSame(['Абрикос', 'Вишня', 'Яблуко'], $labels);
    }

    /**
     * Scenario: defaultLocale is in availableLocales — use it (intersection logic).
     */
    public function test_translatable_sort_uses_default_locale_when_it_is_available(): void
    {
        config()->set('app.locale', 'uk');
        config()->set('bpadmin.locales', ['en', 'uk']);

        // Store content in BOTH locales; the 'uk' locale order should win.
        DB::table('test_translatable_items')->insert([
            ['title' => json_encode(['en' => 'Zebra', 'uk' => 'Зебра']),    'created_at' => now(), 'updated_at' => now()],
            ['title' => json_encode(['en' => 'Apple', 'uk' => 'Яблуко']),   'created_at' => now(), 'updated_at' => now()],
            ['title' => json_encode(['en' => 'Cherry', 'uk' => 'Вишня']),   'created_at' => now(), 'updated_at' => now()],
        ]);

        $definition = $this->makeDefinition();

        $result = $this->repository->list(
            $definition,
            new Criteria(sort: [new Sort('title', 'asc')]),
        );

        // Sorted by 'uk' values because app.locale=uk ∈ availableLocales.
        // SQLite byte-order for UTF-8 Cyrillic: В(0x412) < З(0x417) < Я(0x42F).
        $labels = array_map(fn(EntityRecordContract $item): mixed => json_decode((string) $item->get('title'), true)['uk'], $result->items);

        self::assertSame(['Вишня', 'Зебра', 'Яблуко'], $labels);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @param array<int, array<string, string>> $localizedValues e.g. [['uk' => 'foo']]
     */
    private function insertRows(array $localizedValues): void
    {
        foreach ($localizedValues as $localized) {
            DB::table('test_translatable_items')->insert([
                'title'      => json_encode($localized),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function makeDefinition(): EntityDefinition
    {
        return new class extends EntityDefinition {
            public string $model = TestTranslatableItem::class;

            public function resolveName(): string
            {
                return 'test_translatable_item';
            }

            public function fields(): array
            {
                return [
                    TranslatableField::make('title')->sortable(),
                ];
            }
        };
    }
}

// ---------------------------------------------------------------------------
// Inline model fixture
// ---------------------------------------------------------------------------

use Illuminate\Database\Eloquent\Model;

/**
 * Minimal Eloquent model backed by test_translatable_items table.
 *
 * @property int $id
 * @property string $title JSON blob: {"uk":"...", "en":"..."}
 */
class TestTranslatableItem extends Model
{
    protected $table    = 'test_translatable_items';
    protected $fillable = ['title'];
}
