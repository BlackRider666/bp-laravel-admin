<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Http;

use BlackParadise\CoreAdmin\Domain\Contracts\Action\ActionContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Auth\AuthorizationProviderContract;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Http\Presenters\EntityPresenterInterface;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\BootsBPAdmin;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\StubsValueHasher;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItem;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Mockery;

/**
 * A minimal ActionContract implementation for test fixtures.
 */
final class TestPublishAction implements ActionContract
{
    public function name(): string
    {
        return 'publish';
    }
    public function label(): string
    {
        return 'Publish';
    }
    public function scope(): string
    {
        return 'row';
    }
    public function permission(): ?string
    {
        return null;
    }
    public function confirm(): bool
    {
        return false;
    }
    public function meta(): array
    {
        return [];
    }
}

/**
 * TestItem definition with a 'publish' action.
 */
final class TestItemWithActionDefinition extends EntityDefinition
{
    public string $model = TestItem::class;

    public function resolveName(): string
    {
        return 'test_item';
    }

    public function fields(): array
    {
        return [
            TextField::make('name'),
            TextField::make('email'),
        ];
    }

    public function actions(): array
    {
        return [new TestPublishAction()];
    }
}

/**
 * A15 (B13): action() must route through the presenter in JSON mode.
 *
 * Bug: action() always calls back()->with('success', ...) regardless of the
 * presenter in use — even for JSON clients this returns HTTP 302.
 *
 * Fix: introduce EntityPresenterInterface::actionResult($message, $rowId) and
 * replace the direct back() call in action() with $this->presenter->actionResult(...).
 * JsonEntityPresenter::actionResult() must return JSON 200 with 'message' key.
 */
final class ActionJsonPresenterTest extends TestCase
{
    use RefreshDatabase;
    use BootsBPAdmin;
    use StubsValueHasher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stubValueHasher(); // must be first — prevents LaravelValueHasher fatal

        $this->setUpBPAdmin();

        Schema::create('test_items', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('email')->nullable();
            $t->timestamps();
        });

        /** @var EntityDefinitionRegistry $registry */
        $registry = $this->app->make(EntityDefinitionRegistry::class);
        $registry->register(new TestItemWithActionDefinition());

        $this->app->bind(AuthorizationProviderContract::class, function (): AuthorizationProviderContract {
            $mock = Mockery::mock(AuthorizationProviderContract::class);
            $mock->shouldReceive('can')->andReturn(true);
            return $mock;
        });

        $this->actingAsAdmin();
    }

    // ------------------------------------------------------------------
    // A15 — actionResult() method must exist on EntityPresenterInterface
    // ------------------------------------------------------------------

    /**
     * EntityPresenterInterface must declare actionResult(string $message, ?string $rowId): Response.
     *
     * Currently FAILS: the method does not exist on the interface.
     */
    public function test_presenter_interface_declares_action_result_method(): void
    {
        self::assertTrue(
            method_exists(EntityPresenterInterface::class, 'actionResult'),
            'EntityPresenterInterface must declare actionResult(string, ?string): Response',
        );
    }

    // ------------------------------------------------------------------
    // A15 — JSON action on collection route returns 200 with message
    // ------------------------------------------------------------------

    /**
     * POST /admin/test_item/actions/publish in JSON mode must return HTTP 200
     * with a JSON body containing the 'message' key.
     *
     * Currently FAILS: the controller calls back() which triggers a 302 redirect.
     */
    public function test_action_returns_json_200_with_message_in_json_mode(): void
    {
        TestItem::create(['name' => 'Doc', 'email' => 'doc@example.com']);

        $response = $this->postJson(
            route('bpadmin.entity.action', ['entity' => 'test_item', 'action' => 'publish']),
        );

        $response->assertOk();
        $response->assertJsonStructure(['message']);
    }

    // ------------------------------------------------------------------
    // A15 — JSON action on row route includes row id in response
    // ------------------------------------------------------------------

    /**
     * POST /admin/test_item/{id}/actions/publish must return HTTP 200 with
     * a JSON body. The row id should be accessible.
     *
     * Currently FAILS: returns 302 redirect.
     */
    public function test_action_row_returns_json_200_with_message_in_json_mode(): void
    {
        $item = TestItem::create(['name' => 'Doc', 'email' => 'doc@example.com']);

        $response = $this->postJson(
            route('bpadmin.entity.action.row', ['entity' => 'test_item', 'id' => $item->id, 'action' => 'publish']),
        );

        $response->assertOk();
        $response->assertJsonStructure(['message']);
    }
}
