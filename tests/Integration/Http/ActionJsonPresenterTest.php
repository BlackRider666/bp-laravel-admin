<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Http;

use BlackParadise\CoreAdmin\Domain\Action\ActionContext;
use BlackParadise\CoreAdmin\Domain\Action\ActionOutcome;
use BlackParadise\CoreAdmin\Domain\Action\ActionResult;
use BlackParadise\CoreAdmin\Domain\Action\ActionScope;
use BlackParadise\CoreAdmin\Domain\Contracts\Action\ActionContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Action\DownloadContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Auth\AuthorizationProviderContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Fields\FieldContract;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\CoreAdmin\Domain\ValueObjects\EntityKey;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Http\Presenters\EntityPresenterInterface;
use BlackParadise\LaravelAdmin\Http\Presenters\Json\JsonEntityPresenter;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\BootsBPAdmin;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\StubsValueHasher;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItem;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Mockery;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    /** @return list<ActionScope> */
    public function scopes(): array
    {
        return [ActionScope::Row, ActionScope::Bulk, ActionScope::Entity];
    }

    /** @return list<FieldContract> */
    public function fields(): array
    {
        return [];
    }

    public function handle(ActionContext $context): ActionResult
    {
        return ActionResult::message('Published');
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

    /** @return list<class-string<ActionContract>> */
    public function actions(): array
    {
        return [TestPublishAction::class];
    }
}

/**
 * A15 (B13): action() must route through the presenter in JSON mode.
 *
 * Bug: action() always calls back()->with('success', ...) regardless of the
 * presenter in use — even for JSON clients this returns HTTP 302.
 *
 * Fix: route action() through EntityPresenterInterface::actionResult(ActionResult, EntityDefinitionContract)
 * instead of the direct back() call. JsonEntityPresenter::actionResult() must return JSON 200 with 'message' key.
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
     * EntityPresenterInterface must declare actionResult(ActionResult, EntityDefinitionContract): Response.
     *
     * Currently FAILS: the method does not exist on the interface.
     */
    public function test_presenter_interface_declares_action_result_method(): void
    {
        self::assertTrue(
            method_exists(EntityPresenterInterface::class, 'actionResult'),
            'EntityPresenterInterface must declare actionResult(ActionResult, EntityDefinitionContract): Response',
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

    // ------------------------------------------------------------------
    // A38 — JSON results from an ActionResult (presenter called directly: the controller
    // call site switches to ActionResult in a later task)
    // ------------------------------------------------------------------

    public function test_presenter_action_result_accepts_an_action_result_as_first_parameter(): void
    {
        $type = (new ReflectionMethod(JsonEntityPresenter::class, 'actionResult'))->getParameters()[0]->getType();

        self::assertContains(ActionResult::class, explode('|', ltrim((string) $type, '?')));
    }

    public function test_json_action_result_message_has_outcome_message_and_empty_failed_keys(): void
    {
        $response = $this->renderResult(ActionResult::message('Published'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            ['outcome' => 'success', 'message' => 'Published', 'failedKeys' => []],
            $this->jsonWithout($response, ['id']),
        );
    }

    public function test_json_action_result_carries_the_outcome_level(): void
    {
        $response = $this->renderResult(ActionResult::message('Careful', ActionOutcome::Warning));

        self::assertSame('warning', $this->jsonWithout($response, [])['outcome'] ?? null);
    }

    public function test_json_action_result_redirect_exposes_the_redirect_url_and_message(): void
    {
        $response = $this->renderResult(ActionResult::redirect('/admin/test_item', 'Moved'));

        $body = $this->jsonWithout($response, []);
        self::assertSame('/admin/test_item', $body['redirect'] ?? null);
        self::assertSame('Moved', $body['message'] ?? null);
    }

    public function test_json_action_result_without_redirect_omits_the_redirect_key(): void
    {
        $response = $this->renderResult(ActionResult::message('Plain'));

        self::assertArrayNotHasKey('redirect', $this->jsonWithout($response, []));
    }

    public function test_json_action_result_lists_failed_keys(): void
    {
        $result = ActionResult::message('Partial', ActionOutcome::Warning)
            ->withFailedKeys([new EntityKey(2), new EntityKey(5)]);

        $body = $this->jsonWithout($this->renderResult($result), []);

        self::assertSame(['2', '5'], array_map(strval(...), (array) ($body['failedKeys'] ?? [])));
    }

    public function test_json_action_result_download_streams_the_file_with_name_and_mime(): void
    {
        $download = new class implements DownloadContract {
            public function filename(): string
            {
                return 'report.csv';
            }

            public function mimeType(): string
            {
                return 'text/csv';
            }

            public function stream(): void
            {
                echo "a,b\n1,2\n";
            }
        };

        $response = $this->renderResult(ActionResult::download($download));

        self::assertInstanceOf(StreamedResponse::class, $response);
        self::assertStringContainsString('report.csv', (string) $response->headers->get('Content-Disposition'));
        self::assertStringStartsWith('text/csv', (string) $response->headers->get('Content-Type'));
        ob_start();
        $response->sendContent();
        self::assertSame("a,b\n1,2\n", ob_get_clean());
    }

    private function renderResult(ActionResult $result): Response
    {
        $definition = app()->make(EntityDefinitionRegistry::class)->get('test_item');
        $response = (new ReflectionMethod(JsonEntityPresenter::class, 'actionResult'))->invoke(new JsonEntityPresenter(), $result, $definition);
        self::assertInstanceOf(Response::class, $response);

        return $response;
    }

    /**
     * @param list<string> $drop
     * @return array<string, mixed>
     */
    private function jsonWithout(Response $response, array $drop): array
    {
        /** @var array<string, mixed> $body */
        $body = (array) json_decode((string) $response->getContent(), true);

        return array_diff_key($body, array_flip($drop));
    }
}
