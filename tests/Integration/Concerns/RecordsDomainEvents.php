<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Concerns;

use BlackParadise\CoreAdmin\Domain\Contracts\Events\DomainEventContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Records every delivered domain event of the given classes together with the DB
 * transaction level at delivery time (0 = delivered after the outermost commit).
 */
trait RecordsDomainEvents
{
    /** @var list<array{class: class-string, key: int|string, level: int}> */
    protected array $seenEvents = [];

    /** @param class-string ...$classes */
    protected function recordEvents(string ...$classes): void
    {
        foreach ($classes as $class) {
            Event::listen($class, function (DomainEventContract $event): void {
                $this->seenEvents[] = [
                    'class' => $event::class,
                    'key' => $event->getEntityKey()->value,
                    'level' => DB::transactionLevel(),
                ];
            });
        }
    }

    /**
     * @param class-string $class
     * @return list<int|string>
     */
    protected function seenKeys(string $class): array
    {
        $keys = [];
        foreach ($this->seenEvents as $seen) {
            if ($seen['class'] === $class) {
                $keys[] = $seen['key'];
            }
        }

        return $keys;
    }

    /** @param class-string $class */
    protected function seenCount(string $class): int
    {
        return count($this->seenKeys($class));
    }

    protected function allSeenAfterCommit(): bool
    {
        foreach ($this->seenEvents as $seen) {
            if ($seen['level'] !== 0) {
                return false;
            }
        }

        return true;
    }
}
