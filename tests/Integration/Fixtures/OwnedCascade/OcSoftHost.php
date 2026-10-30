<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\OwnedCascade;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Real soft-deletable host: a plain delete keeps the children, only force-delete cascades. */
final class OcSoftHost extends Model
{
    use SoftDeletes;

    protected $table = 'oc_soft_hosts';
    protected $guarded = [];

    /** What SoftDeletes::restore() does (clears deleted_at); spelled out because static analysis flags restore(). */
    public function restoreFromTrash(): void
    {
        $this->setAttribute($this->getDeletedAtColumn(), null);
        $this->save();
    }

    /** @return HasMany<OcChild, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OcChild::class, 'soft_host_id');
    }
}
