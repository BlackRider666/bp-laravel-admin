<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TestComment extends Model
{
    protected $table = 'test_comments';
    protected $guarded = [];

    public function testItem(): BelongsTo
    {
        return $this->belongsTo(TestItem::class, 'test_item_id');
    }
}
