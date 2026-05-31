<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class TestMorphedFile extends Model
{
    protected $table = 'test_morphed_files';
    protected $guarded = [];

    public function fileable(): MorphTo
    {
        return $this->morphTo();
    }
}
