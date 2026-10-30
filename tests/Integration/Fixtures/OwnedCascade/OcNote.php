<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\OwnedCascade;

use Illuminate\Database\Eloquent\Model;

/** Owned morphMany child with a column file. */
final class OcNote extends Model
{
    protected $table = 'oc_notes';
    protected $guarded = [];
}
