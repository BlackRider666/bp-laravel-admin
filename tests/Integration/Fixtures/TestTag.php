<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures;

use Illuminate\Database\Eloquent\Model;

final class TestTag extends Model
{
    protected $table = 'test_tags';
    protected $guarded = [];
}
