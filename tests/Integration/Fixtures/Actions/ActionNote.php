<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\Actions;

use Illuminate\Database\Eloquent\Model;

final class ActionNote extends Model
{
    protected $table = 'action_notes';
    protected $guarded = [];
}
