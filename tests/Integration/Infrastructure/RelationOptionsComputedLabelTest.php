<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Infrastructure;

use BlackParadise\CoreAdmin\Domain\Contracts\Entity\RelationOptionsProviderContract;
use BlackParadise\CoreAdmin\Domain\Fields\BelongsToField;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesRelationFixtures;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesTestItemTable;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestTag;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class RelationOptionsComputedLabelTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTestItemTable;
    use CreatesRelationFixtures;

    private RelationOptionsProviderContract $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestItemFixtures();
        $this->setUpRelationFixtures();
        $this->provider = $this->app->make(RelationOptionsProviderContract::class);
    }

    public function test_uses_display_callback_for_labels(): void
    {
        $a = TestTag::create(['name' => 'alpha']);
        $b = TestTag::create(['name' => 'beta']);

        $field = BelongsToField::make('test_tag_id', TestTag::class)
            ->withDisplayField('name')
            ->withDisplayUsing(static function (array $row): string {
                $rawName = $row['name'] ?? null;
                $name    = is_string($rawName) ? $rawName : '';
                $rawId   = $row['id'] ?? null;
                $id      = is_string($rawId) || is_int($rawId) ? (string) $rawId : '';
                return strtoupper($name) . ' #' . $id;
            });

        $options = $this->provider->options($field);

        self::assertCount(2, $options);
        $byId = array_column($options, null, 'id');
        self::assertSame('ALPHA #' . $a->id, $byId[$a->id]['label']);
        self::assertSame('BETA #' . $b->id, $byId[$b->id]['label']);
    }

    public function test_sorts_in_php_by_computed_label_when_no_order_column(): void
    {
        TestTag::create(['name' => 'zzz']);
        TestTag::create(['name' => 'aaa']);

        $field = BelongsToField::make('test_tag_id', TestTag::class)
            ->withDisplayField('name')
            ->withDisplayUsing(static function (array $row): string {
                $rawName = $row['name'] ?? null;
                $name    = is_string($rawName) ? $rawName : '';
                return $name === 'zzz' ? 'AAA-first' : 'ZZZ-last';
            });

        $labels = array_column($this->provider->options($field), 'label');
        self::assertSame(['AAA-first', 'ZZZ-last'], $labels);
    }

    public function test_non_callback_path_unchanged(): void
    {
        TestTag::create(['name' => 'beta']);
        TestTag::create(['name' => 'alpha']);

        $field   = BelongsToField::make('test_tag_id', TestTag::class)->withDisplayField('name');
        $labels  = array_column($this->provider->options($field), 'label');
        self::assertSame(['alpha', 'beta'], $labels); // SQL orderBy(name), raw column
    }
}
