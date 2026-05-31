<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Infrastructure;

use BlackParadise\CoreAdmin\Domain\Contracts\Entity\RelationOptionsProviderContract;
use BlackParadise\CoreAdmin\Domain\Fields\BelongsToField;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\EloquentRelationOptionsProvider;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesRelationFixtures;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesTestItemTable;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestTag;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Verifies that EloquentRelationOptionsProvider returns id+label rows shaped
 * for direct JSON serialisation into form meta.
 */
final class EloquentRelationOptionsProviderTest extends TestCase
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

    public function test_returns_id_label_rows_ordered_by_display_field(): void
    {
        TestTag::create(['name' => 'gamma']);
        TestTag::create(['name' => 'alpha']);
        TestTag::create(['name' => 'beta']);

        $field = BelongsToField::make('test_tag_id', TestTag::class);

        $options = $this->provider->options($field);

        $this->assertCount(3, $options);
        $this->assertSame('alpha', $options[0]['label']);
        $this->assertSame('beta', $options[1]['label']);
        $this->assertSame('gamma', $options[2]['label']);
        foreach ($options as $row) {
            $this->assertArrayHasKey('id', $row);
            $this->assertArrayHasKey('label', $row);
            $this->assertNotNull($row['id']);
        }
    }

    public function test_respects_limit_argument(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            TestTag::create(['name' => sprintf('tag-%02d', $i)]);
        }

        $field   = BelongsToField::make('test_tag_id', TestTag::class);
        $options = $this->provider->options($field, 2);

        $this->assertCount(2, $options);
        $this->assertSame('tag-01', $options[0]['label']);
        $this->assertSame('tag-02', $options[1]['label']);
    }

    public function test_returns_empty_array_for_missing_target_class(): void
    {
        $field = BelongsToField::make('test_tag_id', '\\Does\\Not\\Exist');

        $provider = new EloquentRelationOptionsProvider();
        $options  = $provider->options($field);
        $this->assertSame([], $options);
    }
}
