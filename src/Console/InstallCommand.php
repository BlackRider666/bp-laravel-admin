<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Console;

use BlackParadise\LaravelAdmin\EntityDefinition;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Artisan command that scaffolds EntityDefinition classes from Eloquent models.
 *
 * Workflow:
 *   1. Reads `config('bpadmin.entities')` — expects Eloquent model class strings.
 *   2. Skips any entry that is already an EntityDefinition subclass.
 *   3. Generates `app/BPAdmin/{ClassName}.php` scaffold for each model.
 *
 * The config is used only for code generation and is never modified.
 * BPAdmin auto-discovers all EntityDefinition classes in app/BPAdmin/ at runtime.
 *
 * Re-running is safe: existing files are skipped unless --force is passed.
 */
final class InstallCommand extends Command
{
    protected $signature = 'bpadmin:install {--force : Overwrite existing EntityDefinition files}';

    protected $description = 'Scaffold App\\BPAdmin EntityDefinition classes from Eloquent models listed in config/bpadmin.php';

    public function handle(): int
    {
        $this->info('BPAdmin: reading config/bpadmin.php...');

        /** @var list<class-string> $entries */
        $entries = config('bpadmin.entities', []);

        if (empty($entries)) {
            $this->warn('No entries found in config/bpadmin.php → entities.');
            $this->line('Add your Eloquent model classes and re-run this command:');
            $this->line("  'entities' => [\\App\\Models\\User::class],");

            return self::SUCCESS;
        }

        foreach ($entries as $class) {
            if (!class_exists($class)) {
                $this->warn("  Skipping {$class}: class not found.");
                continue;
            }

            if (is_subclass_of($class, EntityDefinition::class)) {
                $this->line("  Skipping {$class}: already an EntityDefinition.");
                continue;
            }

            $this->generateDefinition($class);
        }

        $this->newLine();
        $this->info('Done. Open your App\\BPAdmin classes and define fields().');

        return self::SUCCESS;
    }

    /**
     * Generate the EntityDefinition scaffold for the given Eloquent model class.
     *
     * @param class-string $modelClass
     */
    private function generateDefinition(string $modelClass): void
    {
        $modelBasename  = class_basename($modelClass);
        $definitionFqcn = 'App\\BPAdmin\\' . $modelBasename;
        $targetPath     = app_path('BPAdmin' . DIRECTORY_SEPARATOR . $modelBasename . '.php');

        if (File::exists($targetPath) && !$this->option('force')) {
            $this->line("  Skipping {$definitionFqcn}: file already exists (use --force to overwrite).");
            return;
        }

        File::ensureDirectoryExists(app_path('BPAdmin'));
        File::put($targetPath, $this->buildStub($modelBasename, $modelClass));

        $this->info("  Generated: {$targetPath}");
    }

    /**
     * Build the PHP source for an EntityDefinition scaffold.
     *
     * @param class-string $modelClass
     */
    private function buildStub(string $className, string $modelClass): string
    {
        $modelFqcn = '\\' . ltrim($modelClass, '\\');

        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace App\BPAdmin;

        use BlackParadise\LaravelAdmin\EntityDefinition;

        class {$className} extends EntityDefinition
        {
            public string \$model = {$modelFqcn}::class;

            protected function defineFields(): array
            {
                return [
                    // TODO: define fields for {$className}
                ];
            }
        }
        PHP;
    }
}
