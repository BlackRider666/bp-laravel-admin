<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Hashing;

use BlackParadise\CoreAdmin\Domain\Contracts\ValueHasherContract;
use Illuminate\Support\Facades\Hash;

final class LaravelValueHasher implements ValueHasherContract
{
    public function hash(string $value): string
    {
        return Hash::make($value);
    }
}
