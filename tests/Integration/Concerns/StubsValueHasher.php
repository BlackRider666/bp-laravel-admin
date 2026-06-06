<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Concerns;

use BlackParadise\CoreAdmin\Domain\Contracts\ValueHasherContract;

/**
 * Test helper: override the ValueHasherContract binding with a minimal stub that
 * implements isHashed().
 *
 * Context: the core package has already declared isHashed() on ValueHasherContract,
 * but LaravelValueHasher does not yet implement it. This causes a PHP fatal when
 * the container tries to instantiate LaravelValueHasher. Tests that exercise code
 * paths resolving EntityMutatorInterface (which depends on ValueHasherContract)
 * must call stubValueHasher() BEFORE any container resolution to avoid the fatal.
 *
 * Usage:
 *   protected function setUp(): void {
 *       parent::setUp();
 *       $this->stubValueHasher(); // must be called FIRST
 *       // ... rest of setUp
 *   }
 */
trait StubsValueHasher
{
    /**
     * Override ValueHasherContract binding with a stub that implements isHashed().
     * Call this at the TOP of setUp(), before any container resolution.
     */
    protected function stubValueHasher(): void
    {
        $this->app->bind(ValueHasherContract::class, fn(): ValueHasherContract => new class implements ValueHasherContract {
            public function hash(string $value): string
            {
                return 'stub_hashed_' . $value;
            }

            public function isHashed(string $value): bool
            {
                return str_starts_with($value, '$2y$') // bcrypt
                    || str_starts_with($value, '$argon') // argon
                    || str_starts_with($value, 'stub_hashed_');
            }
        });
    }
}
