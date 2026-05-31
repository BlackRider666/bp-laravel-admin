<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TestPost extends Model
{
    protected $table = 'test_posts';
    protected $guarded = [];

    public function author(): BelongsTo
    {
        return $this->belongsTo(TestAuthor::class, 'author_id');
    }
}
