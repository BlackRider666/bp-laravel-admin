<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures;

use Illuminate\Database\Eloquent\Model;

final class TestProfile extends Model
{
    protected $table = 'test_profiles';
    protected $guarded = [];
}
