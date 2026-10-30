<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\CustomPersister;

use BlackParadise\CoreAdmin\Domain\Fields\Base\AbstractField;

/** Host-defined custom field type `slug`. */
final class SlugField extends AbstractField
{
    public static function make(string $name): self
    {
        return new self($name);
    }

    public function type(): string
    {
        return 'slug';
    }
}
