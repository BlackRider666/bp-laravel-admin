<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use RuntimeException;

/**
 * Variant of TestAccount whose `deleting` event always throws a RuntimeException.
 * Used to provoke a transaction rollback in delete-path tests.
 */
final class TestAccountThatFailsDelete extends Model
{
    protected $table = 'test_accounts';
    protected $guarded = [];

    protected static function booted(): void
    {
        self::deleting(static function (): never {
            throw new RuntimeException('forced delete failure');
        });
    }

    public function files(): MorphMany
    {
        return $this->morphMany(TestMorphedFile::class, 'fileable');
    }
}
