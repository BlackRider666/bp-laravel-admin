<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\GraphSave;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class GsHost extends Model
{
    protected $table = 'gs_hosts';
    protected $guarded = [];

    /** @return HasMany<GsItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(GsItem::class, 'host_id');
    }

    /** @return HasOne<GsNote, $this> */
    public function note(): HasOne
    {
        return $this->hasOne(GsNote::class, 'host_id');
    }
}
