<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Feature\Http;

use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesOwnedCascadeFixtures;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\OwnedCascade\OcSoftHost;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Fix round 3.4 (I-1): force-delete cascade of owned children runs in the AMBIENT transaction.
 * A rolled back ambient transaction must leave the children rows, pivot rows and files untouched. No RefreshDatabase on purpose.
 */
final class OwnedCascadeForceDeleteAtomicityTest extends TestCase
{
    use CreatesOwnedCascadeFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOwnedCascadeFixtures();
    }

    public function test_force_delete_inside_a_rolled_back_ambient_transaction_keeps_children_rows_and_pivots(): void
    {
        $host = $this->seedSoftHostGraph();

        try {
            DB::transaction(function () use ($host): never {
                $host->forceDelete();
                throw new RuntimeException('ambient fails');
            });
        } catch (RuntimeException) {
            // expected
        }

        $this->assertDatabaseCount('oc_children', 2);
        self::assertSame(3, $this->pivotRows());
    }

    public function test_force_delete_inside_a_rolled_back_ambient_transaction_keeps_children_files(): void
    {
        $host = $this->seedSoftHostGraph();

        try {
            DB::transaction(function () use ($host): never {
                $host->forceDelete();
                throw new RuntimeException('ambient fails');
            });
        } catch (RuntimeException) {
            // expected
        }

        Storage::disk('archive')->assertExists('oc/alpha.bin');
        Storage::disk('archive')->assertExists('oc/beta.bin');
    }

    public function test_force_delete_inside_a_committed_ambient_transaction_removes_children_files_only_after_commit(): void
    {
        $host = $this->seedSoftHostGraph();
        $existedInside = false;

        DB::transaction(function () use ($host, &$existedInside): void {
            $host->forceDelete();
            $existedInside = Storage::disk('archive')->exists('oc/alpha.bin');
        });

        self::assertTrue($existedInside, 'Children files must wait for the ambient commit.');
        Storage::disk('archive')->assertMissing('oc/alpha.bin');
        $this->assertDatabaseCount('oc_children', 0);
    }

    public function test_force_delete_without_failure_commits_host_and_children_together(): void
    {
        $host = $this->seedSoftHostGraph();

        $host->forceDelete();

        self::assertNull(OcSoftHost::withTrashed()->find($host->id));
        $this->assertDatabaseCount('oc_children', 0);
        self::assertSame(0, $this->pivotRows());
        Storage::disk('archive')->assertMissing('oc/beta.bin');
    }
}
