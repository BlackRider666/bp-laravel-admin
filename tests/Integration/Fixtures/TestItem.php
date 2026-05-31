<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Minimal Eloquent model used as a fixture in integration tests.
 *
 * Maps to the `test_items` table created in-memory by
 * {@see \BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesTestItemTable}.
 */
final class TestItem extends Model
{
    protected $table = 'test_items';

    protected $guarded = [];

    public function category(): BelongsTo
    {
        return $this->belongsTo(TestCategory::class, 'category_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(TestTag::class, 'test_item_tag', 'test_item_id', 'test_tag_id')
            ->withPivot('approved');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TestComment::class, 'test_item_id');
    }

    public function profile(): HasOne
    {
        return $this->hasOne(TestProfile::class, 'test_item_id');
    }

    public function morphComments(): MorphMany
    {
        return $this->morphMany(TestMorphComment::class, 'commentable');
    }
}
