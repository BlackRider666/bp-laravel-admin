<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Child model for deep-relation eager-load tests.
 *
 * Has a belongsToMany relation to TestTag, used to verify that
 * embedded sub-definitions trigger nested eager-loading and
 * deep serialization in EloquentEntityRepository.
 */
final class TestPublicationItem extends Model
{
    protected $table = 'test_publication_items';
    protected $guarded = [];

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(TestTag::class, 'test_pub_item_tag', 'pub_item_id', 'test_tag_id');
    }
}
