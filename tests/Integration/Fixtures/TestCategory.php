<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * Minimal Eloquent model used as a fixture in eager-loading integration tests.
 *
 * Maps to the `test_categories` table created inline in eager-loading test methods.
 */
final class TestCategory extends Model
{
    protected $table = 'test_categories';

    protected $guarded = [];
}
