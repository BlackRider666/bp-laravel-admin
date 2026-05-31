<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Console;

use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\Support\AvailableLocalesResolver;
use Illuminate\Console\Command;

/**
 * Generates translation stubs for every registered EntityDefinition.
 *
 * Writes app()->langPath('vendor/bpadmin/{locale}/entities.php') shaped as:
 *   [
 *     '{entity}' => [
 *       '_label' => 'Entity',
 *       '{field}' => 'Field name',
 *     ],
 *   ]
 *
 * Default: recursive merge — existing translations preserved, new keys added.
 * --force: overwrite all values with freshly generated defaults.
 */
final class GenerateTranslationCommand extends Command
{
    protected $signature = 'bpadmin:translations {--lang=* : Target locales, default = all discovered} {--force : Overwrite existing values}';

    protected $description = 'Generate translation stubs for all registered entities and their fields';

    public function __construct(
        private readonly EntityDefinitionRegistry $registry,
        private readonly AvailableLocalesResolver $resolver,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $locales = $this->option('lang');
        if ($locales === []) {
            $locales = $this->resolver->list();
        }

        $force = (bool) $this->option('force');

        foreach ($locales as $locale) {
            $this->processLocale((string) $locale, $force);
        }

        return self::SUCCESS;
    }

    private function processLocale(string $locale, bool $force): void
    {
        $dir  = app()->langPath('vendor/bpadmin/' . $locale);
        $path = $dir . '/entities.php';

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $fresh    = $this->buildFreshTree();
        $existing = is_file($path) ? (array) require $path : [];

        $result = $force ? $fresh : array_replace_recursive($fresh, $existing);

        $this->writePhpArray($path, $result);
        $this->info("Written: {$path}");
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function buildFreshTree(): array
    {
        $tree = [];

        foreach ($this->registry->all() as $name => $definition) {
            $tree[$name] = [
                '_label' => ucfirst((string) $name),
            ];

            foreach ($definition->fields() as $field) {
                $fieldName = $field->name();
                $tree[$name][$fieldName] = ucfirst(str_replace('_', ' ', $fieldName));
            }
        }

        return $tree;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function writePhpArray(string $path, array $data): void
    {
        $body = "<?php\n\nreturn " . var_export($data, true) . ";\n";
        file_put_contents($path, $body);
    }
}
