<?php

declare(strict_types=1);

/*
 * Pest bootstrap for bp-laravel-admin.
 *
 * Існуючі PHPUnit-тести в tests/Unit/, tests/Integration/, tests/Feature/
 * продовжують працювати через Pest's compat layer. Нові arch-тести —
 * у tests/Architecture/.
 */

use BlackParadise\LaravelAdmin\Tests\TestCase;

uses(TestCase::class)->in(__DIR__ . '/Integration', __DIR__ . '/Feature');
