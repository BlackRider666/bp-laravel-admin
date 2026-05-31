<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Feature\Console;

use BlackParadise\CoreAdmin\Domain\Contracts\Fields\FieldContract;
use BlackParadise\CoreAdmin\Domain\Validation\RuleSet;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\TestCase;

final class GenerateTranslationCommandTest extends TestCase
{
    private string $langPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->langPath = $this->app->langPath('vendor/bpadmin');

        /** @var EntityDefinitionRegistry $registry */
        $registry = $this->app->make(EntityDefinitionRegistry::class);

        $registry->register(
            $this->fakeDefinition('users', [
                $this->fakeField('email'),
                $this->fakeField('full_name'),
            ]),
        );
    }

    protected function tearDown(): void
    {
        if (is_dir($this->langPath)) {
            $this->deleteDir($this->langPath);
        }

        parent::tearDown();
    }

    public function test_creates_file_with_entity_and_fields(): void
    {
        $this->artisan('bpadmin:translations', ['--lang' => ['en']])
            ->assertSuccessful();

        $path = $this->langPath . '/en/entities.php';
        self::assertFileExists($path);

        $data = require $path;

        self::assertSame('Users', $data['users']['_label']);
        self::assertSame('Email', $data['users']['email']);
        self::assertSame('Full name', $data['users']['full_name']);
    }

    public function test_merge_preserves_existing_translations_and_adds_new_keys(): void
    {
        $dir = $this->langPath . '/uk';
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/entities.php', "<?php\nreturn " . var_export([
            'users' => [
                '_label' => 'Користувачі',
                'email'  => 'Електронна пошта',
            ],
        ], true) . ";\n");

        $this->artisan('bpadmin:translations', ['--lang' => ['uk']])
            ->assertSuccessful();

        $data = require $this->langPath . '/uk/entities.php';

        self::assertSame('Користувачі', $data['users']['_label']);
        self::assertSame('Електронна пошта', $data['users']['email']);
        self::assertSame('Full name', $data['users']['full_name']);
    }

    public function test_force_overwrites_existing_values(): void
    {
        $dir = $this->langPath . '/uk';
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/entities.php', "<?php\nreturn " . var_export([
            'users' => [
                '_label' => 'Користувачі',
                'email'  => 'Електронна пошта',
            ],
        ], true) . ";\n");

        $this->artisan('bpadmin:translations', ['--lang' => ['uk'], '--force' => true])
            ->assertSuccessful();

        $data = require $this->langPath . '/uk/entities.php';

        self::assertSame('Users', $data['users']['_label']);
        self::assertSame('Email', $data['users']['email']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function fakeField(string $name): FieldContract
    {
        return new class ($name) implements FieldContract {
            public function __construct(private readonly string $n) {}

            public function name(): string
            {
                return $this->n;
            }
            public function label(): string
            {
                return ucfirst(str_replace('_', ' ', $this->n));
            }
            public function type(): string
            {
                return 'text';
            }
            public function rules(): array
            {
                return [];
            }
            public function ruleSet(): RuleSet
            {
                return new RuleSet([]);
            }
            public function visibleOnList(): bool
            {
                return true;
            }
            public function visibleOnForm(): bool
            {
                return true;
            }
            public function visibleOnShow(): bool
            {
                return true;
            }
            public function isSortable(): bool
            {
                return false;
            }
            public function isFilterable(): bool
            {
                return false;
            }
            public function meta(): array
            {
                return [];
            }
            public function writable(): bool
            {
                return true;
            }
        };
    }

    private function fakeDefinition(string $name, array $fields): EntityDefinition
    {
        return new class ($name, $fields) extends EntityDefinition {
            public function __construct(private readonly string $n, private readonly array $f) {}
            public function resolveName(): string
            {
                return $this->n;
            }
            public function label(): string
            {
                return ucfirst($this->n);
            }
            public function fields(): array
            {
                return $this->f;
            }
        };
    }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.') {
                continue;
            }
            if ($entry === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $entry;

            if (is_dir($path)) {
                $this->deleteDir($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
