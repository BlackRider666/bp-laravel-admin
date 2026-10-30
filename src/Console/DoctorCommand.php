<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Console;

use BlackParadise\LaravelAdmin\Core\DefinitionValidator;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use Illuminate\Console\Command;

/** Lists every definition violation (instead of failing on the first one at boot). */
final class DoctorCommand extends Command
{
    protected $signature = 'bpadmin:doctor {--json : Output violations as a JSON list}';

    protected $description = 'Validate registered BPAdmin entity definitions and report every violation.';

    public function handle(EntityDefinitionRegistry $registry, DefinitionValidator $validator): int
    {
        $violations = $validator->validate($registry);

        if ((bool) $this->option('json')) {
            $this->line(json_encode($violations, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

            return $violations === [] ? self::SUCCESS : self::FAILURE;
        }

        if ($violations === []) {
            $this->info('BPAdmin doctor: no violations found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Definition', 'Subject', 'Rule', 'Message'],
            array_map(static fn(array $v): array => [$v['definition'], $v['subject'], $v['rule'], $v['message']], $violations),
        );
        $this->error(sprintf('%d violation(s) found.', count($violations)));

        return self::FAILURE;
    }
}
