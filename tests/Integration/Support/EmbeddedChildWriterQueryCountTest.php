<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Support;

use BlackParadise\CoreAdmin\Domain\Entity\EntityRecord;
use BlackParadise\CoreAdmin\Domain\Fields\HasOneField;
use BlackParadise\LaravelAdmin\Support\EmbeddedChildWriter;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItem;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItemDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestProfile;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Verifies that EmbeddedChildWriter::writeAll() does NOT re-fetch the host
 * model from the database. Instead it must build a stub model with the host
 * PK set so Eloquent can wire the FK on the child — no SELECT should be issued.
 *
 * Before the optimisation: findOrFail($host->id()) issues one SELECT for the
 * host, then one INSERT for the child → 2 queries total.
 * After the optimisation: stub host model built via newInstance() + forceFill()
 * → only one INSERT for the child → 1 query total.
 */
final class EmbeddedChildWriterQueryCountTest extends TestCase
{
    use RefreshDatabase;

    private TestItemDefinition $hostDefinition;
    private EmbeddedChildWriter $writer;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('test_items', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('email')->default('');
            $t->timestamps();
        });

        Schema::create('test_profiles', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('test_item_id');
            $t->string('bio')->nullable();
            $t->timestamps();
        });

        $this->hostDefinition = new TestItemDefinition();
        $this->writer         = new EmbeddedChildWriter();
    }

    /**
     * writeAll() must issue exactly ONE query (the child INSERT) for a single
     * hasOne child, regardless of how many fields the host has.
     *
     * A SELECT on the host model is the bug: it means writeAll() is re-fetching
     * the host from the database instead of building a stub from the already-known PK.
     */
    public function test_write_all_issues_single_insert_without_re_fetching_host(): void
    {
        // Seed the host record directly — no EmbeddedChildWriter involvement here.
        $item       = TestItem::create(['name' => 'Host', 'email' => 'host@test.com']);
        $hostRecord = new EntityRecord($this->hostDefinition, ['id' => $item->id, 'name' => 'Host']);

        $profileField = HasOneField::make('profile', TestProfile::class);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->writer->writeAll(
            $this->hostDefinition,
            $hostRecord,
            [
                'profile' => [
                    'field'   => $profileField,
                    'payload' => ['bio' => 'Hello world'],
                ],
            ],
        );

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        self::assertCount(
            1,
            $queries,
            sprintf(
                'writeAll() must issue exactly 1 query (child INSERT); got %d. '
                . 'Extra queries indicate a host re-fetch: %s',
                count($queries),
                implode('; ', array_column($queries, 'query')),
            ),
        );

        // Confirm the child was actually persisted with the correct FK.
        self::assertDatabaseHas('test_profiles', [
            'test_item_id' => $item->id,
            'bio'          => 'Hello world',
        ]);
    }
}
