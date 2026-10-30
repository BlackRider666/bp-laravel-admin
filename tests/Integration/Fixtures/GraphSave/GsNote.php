<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\GraphSave;

use Illuminate\Database\Eloquent\Model;

final class GsNote extends Model
{
    protected $table = 'gs_notes';
    protected $guarded = [];
}
