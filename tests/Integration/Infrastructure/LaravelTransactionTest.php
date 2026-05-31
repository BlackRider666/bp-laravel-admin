<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Infrastructure;

use BlackParadise\CoreAdmin\Domain\Contracts\TransactionContract;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\LaravelTransaction;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class LaravelTransactionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('tx_test', function (Blueprint $table): void {
            $table->id();
            $table->string('value');
        });
    }

    public function test_returns_callable_result(): void
    {
        $tx = new LaravelTransaction();

        $result = $tx->executeInTransaction(fn(): string => 'committed');

        self::assertSame('committed', $result);
    }

    public function test_commits_on_successful_return(): void
    {
        $tx = new LaravelTransaction();

        $tx->executeInTransaction(function (): void {
            DB::table('tx_test')->insert(['value' => 'persisted']);
        });

        self::assertSame(1, DB::table('tx_test')->count());
    }

    public function test_rolls_back_on_exception(): void
    {
        $tx = new LaravelTransaction();

        $caught = null;
        try {
            $tx->executeInTransaction(function (): never {
                DB::table('tx_test')->insert(['value' => 'should-rollback']);
                throw new RuntimeException('boom');
            });
        } catch (RuntimeException $e) {
            $caught = $e;
        }

        self::assertInstanceOf(RuntimeException::class, $caught, 'Exception expected');
        self::assertSame('boom', $caught->getMessage());
        self::assertSame(0, DB::table('tx_test')->count());
    }

    public function test_implements_transaction_contract(): void
    {
        self::assertInstanceOf(TransactionContract::class, new LaravelTransaction());
    }
}
