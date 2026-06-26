<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Host model for deep-relation eager-load tests.
 *
 * Has a hasMany relation to TestPublicationItem, where the item
 * definition is embedded (->embed()) and itself contains a belongsToMany.
 * This combination exercises nested dot-path eager-loading and deep
 * serialization in EloquentEntityRepository.
 */
final class TestPublication extends Model
{
    protected $table = 'test_publications';
    protected $guarded = [];

    public function items(): HasMany
    {
        return $this->hasMany(TestPublicationItem::class, 'publication_id');
    }
}
