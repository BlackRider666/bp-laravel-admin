<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\GraphSave;

use Illuminate\Database\Eloquent\Model;

final class GsItem extends Model
{
    protected $table = 'gs_items';
    protected $guarded = [];
}
