<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ClearFile;

use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestMorphedFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class ClearMorphHost extends Model
{
    use SoftDeletes;

    protected $table = 'clear_morph_hosts';
    protected $guarded = [];

    /** @return MorphMany<TestMorphedFile, $this> */
    public function files(): MorphMany
    {
        return $this->morphMany(TestMorphedFile::class, 'fileable');
    }
}
