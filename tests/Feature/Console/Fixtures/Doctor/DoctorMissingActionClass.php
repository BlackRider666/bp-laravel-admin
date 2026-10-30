<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Feature\Console\Fixtures\Doctor;

use BlackParadise\CoreAdmin\Domain\Contracts\Action\ActionContract;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\Actions\ActionNote;

/** Violation: actions() lists a class that does not exist. */
final class DoctorMissingActionClass extends EntityDefinition
{
    public const MISSING = 'App\\BPAdmin\\Actions\\DoesNotExistAction';

    public string $model = ActionNote::class;

    public function fields(): array
    {
        return [TextField::make('title')];
    }

    /** @return list<class-string<ActionContract>> */
    public function actions(): array
    {
        return [self::MISSING]; // @phpstan-ignore return.type
    }
}
