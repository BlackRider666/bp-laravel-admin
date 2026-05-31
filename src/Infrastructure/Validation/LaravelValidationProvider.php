<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Validation;

use BlackParadise\CoreAdmin\Domain\Contracts\Validation\ValidationProviderContract;
use BlackParadise\CoreAdmin\Domain\Exceptions\ValidationException;
use Illuminate\Contracts\Validation\Factory;

final readonly class LaravelValidationProvider implements ValidationProviderContract
{
    public function __construct(
        private Factory $factory,
    ) {}

    public function validate(array $data, array $rules): void
    {
        $validator = $this->factory->make($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator->errors()->toArray());
        }
    }
}
