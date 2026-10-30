<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\OwnedCascade;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** NOT soft-deletable: deleting it must cascade to the owned children. */
final class OcHost extends Model
{
    protected $table = 'oc_hosts';
    protected $guarded = [];

    /** @return HasMany<OcChild, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OcChild::class, 'host_id');
    }

    /** @return MorphMany<OcNote, $this> */
    public function notes(): MorphMany
    {
        return $this->morphMany(OcNote::class, 'notable');
    }
}
