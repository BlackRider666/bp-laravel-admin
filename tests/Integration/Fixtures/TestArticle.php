<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * Мімікує Spatie-translatable: колонка 'title' casts as 'array' →
 * Eloquent сам json_encode'ає при save. Mutator має це детектувати і
 * не подвійно кодувати.
 */
final class TestArticle extends Model
{
    protected $table = 'test_articles';
    protected $guarded = [];
    protected $casts = [
        'title' => 'array',
    ];
}
