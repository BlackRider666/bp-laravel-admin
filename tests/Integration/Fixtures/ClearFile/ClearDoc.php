<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ClearFile;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Host with optional / required / requiredOnCreate column files (soft-deletable). */
final class ClearDoc extends Model
{
    use SoftDeletes;

    protected $table = 'clear_docs';
    protected $guarded = [];
}
