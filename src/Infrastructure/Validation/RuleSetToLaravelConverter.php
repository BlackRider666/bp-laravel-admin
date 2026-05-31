<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Validation;

use BlackParadise\CoreAdmin\Domain\Validation\RuleSet;

final class RuleSetToLaravelConverter
{
    /**
     * Convert a domain RuleSet to a Laravel-compatible rules array.
     *
     * @return array<string>
     */
    public static function convert(RuleSet $ruleSet): array
    {
        return $ruleSet->toArray();
    }
}
