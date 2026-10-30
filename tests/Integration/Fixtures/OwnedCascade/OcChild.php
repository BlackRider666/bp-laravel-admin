<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\OwnedCascade;

use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestTag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Owned hasMany child: a column file and a belongsToMany pivot WITHOUT any foreign key. */
final class OcChild extends Model
{
    protected $table = 'oc_children';
    protected $guarded = [];

    /** @return BelongsToMany<TestTag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(TestTag::class, 'oc_child_tag', 'oc_child_id', 'test_tag_id');
    }
}
