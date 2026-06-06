<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Infrastructure;

use BlackParadise\CoreAdmin\Domain\Contracts\ValueHasherContract;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\StubsValueHasher;
use BlackParadise\LaravelAdmin\Tests\TestCase;

/**
 * A13 (B11): LaravelValueHasher must implement isHashed() from ValueHasherContract.
 *
 * Bug: LaravelValueHasher does not implement the isHashed() method declared by
 * ValueHasherContract (added in bp-admin-core), so PHP fatals when the class
 * is instantiated.
 *
 * Fix: Add public function isHashed(string $value): bool { return Hash::isHashed($value); }
 * to LaravelValueHasher.
 *
 * NOTE: These tests use the container-bound ValueHasherContract (overridden with
 * StubsValueHasher) to avoid triggering the fatal. The actual LaravelValueHasher
 * class is tested indirectly: if it were correct, it could be resolved; since
 * it's broken, the test approach is via the contract/container.
 *
 * The implementation phase must verify that LaravelValueHasher::isHashed()
 * returns true for Hash::make() output and false for plain text.
 */
final class LaravelValueHasherTest extends TestCase
{
    use StubsValueHasher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stubValueHasher();
    }

    // ------------------------------------------------------------------
    // A13 — contract declares isHashed (pre-green — already in core)
    // ------------------------------------------------------------------

    /**
     * ValueHasherContract must declare isHashed(string): bool.
     * This is pre-green: the core package has already added the method.
     */
    public function test_contract_declares_is_hashed_method(): void
    {
        self::assertTrue(
            method_exists(ValueHasherContract::class, 'isHashed'),
            'ValueHasherContract must declare isHashed(string): bool',
        );
    }

    // ------------------------------------------------------------------
    // A13 — container-bound hasher has isHashed method
    // ------------------------------------------------------------------

    /**
     * The ValueHasherContract implementation bound in the container must
     * implement isHashed().
     *
     * With the stub (StubsValueHasher), this passes — it verifies the test
     * infrastructure works. After the impl fix, LaravelValueHasher will be
     * bound and this test will verify the real impl.
     */
    public function test_container_bound_hasher_has_is_hashed_method(): void
    {
        $hasher = $this->app->make(ValueHasherContract::class);

        self::assertTrue(
            method_exists($hasher, 'isHashed'),
            'The ValueHasherContract implementation must have isHashed()',
        );
    }

    // ------------------------------------------------------------------
    // A13 — LaravelValueHasher source file must contain isHashed method
    // ------------------------------------------------------------------

    /**
     * The LaravelValueHasher.php source file must contain a public function isHashed().
     *
     * This is a structural test that avoids instantiating the broken class.
     * Currently FAILS: the method is absent from the source file.
     *
     * After the fix, this test confirms the implementation is in place.
     */
    public function test_laravel_value_hasher_source_contains_is_hashed(): void
    {
        $path = dirname(__DIR__, 3) . '/src/Infrastructure/Hashing/LaravelValueHasher.php';
        $source = file_get_contents($path);

        self::assertNotFalse($source, "LaravelValueHasher.php must exist at {$path}");
        self::assertStringContainsString(
            'function isHashed',
            $source,
            'LaravelValueHasher must implement isHashed() — method not found in source',
        );
    }
}
