<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

final class TestAccount extends Model
{
    protected $table = 'test_accounts';
    protected $guarded = [];

    /**
     * Polymorphic files relation — using MorphMany so that two MorphFileFields
     * ('avatar' and 'image') can coexist on one model, each differentiated by
     * `type` column.
     */
    public function files(): MorphMany
    {
        return $this->morphMany(TestMorphedFile::class, 'fileable');
    }
}
