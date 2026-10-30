<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Feature\Console\Fixtures\Doctor;

use BlackParadise\CoreAdmin\Domain\Contracts\Action\ActionContract;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\Actions\ActionNote;
use stdClass;

/** Violation: actions() lists an existing class that is not an ActionContract. */
final class DoctorNonActionClass extends EntityDefinition
{
    public string $model = ActionNote::class;

    public function fields(): array
    {
        return [TextField::make('title')];
    }

    /** @return list<class-string<ActionContract>> */
    public function actions(): array
    {
        return [stdClass::class]; // @phpstan-ignore return.type
    }
}
