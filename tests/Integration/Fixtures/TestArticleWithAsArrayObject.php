<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;

/**
 * Fixture for class-string cast AsArrayObject — mimics a model where
 * the translatable column is cast with `AsArrayObject::class`.
 * Eloquent serialises the column to JSON on save; mutator must NOT
 * pre-encode or the value is double-encoded.
 */
final class TestArticleWithAsArrayObject extends Model
{
    protected $table = 'test_articles_as_array_object';
    protected $guarded = [];
    protected $casts = [
        'title' => AsArrayObject::class,
    ];
}
