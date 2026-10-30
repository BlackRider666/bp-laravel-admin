<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\BulkPartial;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/** Like TestAccountThatFailsDelete, but only the record named "poison" refuses to be deleted. */
final class BpItem extends Model
{
    protected $table = 'bp_items';
    protected $guarded = [];

    protected static function booted(): void
    {
        self::deleting(static function (BpItem $item): void {
            if ($item->getAttribute('name') === 'poison') {
                throw new RuntimeException('forced delete failure');
            }
        });
    }
}
