<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Feature\Http;

use BlackParadise\CoreAdmin\Domain\Contracts\Auth\AuthorizationProviderContract;
use BlackParadise\CoreAdmin\Domain\Events\ActionExecuted;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\ActsAsPermissiveAdmin;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\RecordsDomainEvents;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\Actions\ActionNote;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\Actions\ActionNoteDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\Actions\ActionProbe;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\Actions\SecretAction;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ScriptedAuthorization;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * A35-A37: the action routes (URLs unchanged) run the action through ExecuteActionUseCase:
 * scope + keys from the route/body, 404/403/422, one write scope, ActionExecuted after commit.
 */
final class ActionRoutesTest extends TestCase
{
    use ActsAsPermissiveAdmin;
    use RecordsDomainEvents;

    private ActionProbe $probe;

    /** @var list<int> archived-row count observed at each ActionExecuted delivery */
    private array $archivedWhenDelivered = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->stubHasher();
        app()->singleton(ActionProbe::class);
        $this->probe = resolve(ActionProbe::class);

        Schema::create('action_notes', function (Blueprint $t): void {
            $t->id();
            $t->string('title');
            $t->string('status')->default('new');
            $t->timestamps();
        });
        foreach (['One', 'Two', 'Three'] as $title) {
            ActionNote::create(['title' => $title]);
        }

        resolve(EntityDefinitionRegistry::class)->register(new ActionNoteDefinition());
        $this->permitEverything();
        $this->loginAdmin();
        $this->recordEvents(ActionExecuted::class);
        Event::listen(ActionExecuted::class, function (): void {
            $this->archivedWhenDelivered[] = DB::table('action_notes')->where('status', 'archived')->count();
        });
    }

    /**
     * @param array<string, mixed> $body
     * @return TestResponse<Response>
     */
    private function postAction(string $action, array $body = [], ?int $rowId = null): TestResponse
    {
        $url = $rowId === null
            ? route('bpadmin.entity.action', ['entity' => 'action_note', 'action' => $action])
            : route('bpadmin.entity.action.row', ['entity' => 'action_note', 'id' => $rowId, 'action' => $action]);

        return $this->postJson($url, $body);
    }

    /** @param list<string> $denied */
    private function deny(array $denied): void
    {
        app()->instance(AuthorizationProviderContract::class, new ScriptedAuthorization($denied));
    }

    private function statusCount(string $status): int
    {
        return DB::table('action_notes')->where('status', $status)->count();
    }

    // ---- A35: scope + keys --------------------------------------------------------------------

    public function test_row_route_runs_the_action_with_row_scope_and_the_single_key(): void
    {
        $this->postAction('archive', [], 2)->assertOk()->assertJsonPath('message', 'Archived 1');

        self::assertCount(1, $this->probe->calls);
        self::assertSame('row', $this->probe->calls[0]['scope']);
        self::assertEquals([2], $this->probe->calls[0]['keys']);
        self::assertSame(1, $this->statusCount('archived'));
        $this->assertDatabaseHas('action_notes', ['id' => 2, 'status' => 'archived']);
    }

    public function test_collection_route_with_keys_runs_the_action_with_bulk_scope(): void
    {
        $this->postAction('archive', ['keys' => [1, 3]])->assertOk()->assertJsonPath('message', 'Archived 2');

        self::assertSame('bulk', $this->probe->calls[0]['scope']);
        self::assertEquals([1, 3], $this->probe->calls[0]['keys']);
        $this->assertDatabaseHas('action_notes', ['id' => 2, 'status' => 'new']);
    }

    public function test_collection_route_without_keys_runs_the_action_with_entity_scope(): void
    {
        $this->postAction('archive')->assertOk();

        self::assertSame('entity', $this->probe->calls[0]['scope']);
        self::assertSame([], $this->probe->calls[0]['keys']);
        self::assertSame(3, $this->statusCount('archived'));
    }

    public function test_action_is_resolved_through_the_container_with_its_dependencies(): void
    {
        $this->postAction('archive', [], 1)->assertOk();

        self::assertSame('archive', $this->probe->calls[0]['action'], 'The action must receive the container-bound probe.');
    }

    public function test_action_input_is_passed_to_handle_when_valid(): void
    {
        $this->postAction('rename', ['input' => ['title' => 'Fresh']])->assertOk()->assertJsonPath('message', 'Renamed to Fresh');

        self::assertSame(['title' => 'Fresh'], $this->probe->calls[0]['input']);
    }

    // ---- A36: 404 / 403 / 422 / validation -----------------------------------------------------

    public function test_unknown_action_returns_404_and_runs_nothing(): void
    {
        $this->postAction('nope')->assertNotFound();

        self::assertSame([], $this->probe->calls);
    }

    public function test_action_without_default_permission_returns_403_and_runs_nothing(): void
    {
        $this->deny(['action.archive']);

        $this->postAction('archive', [], 1)->assertForbidden();

        self::assertSame([], $this->probe->calls);
        self::assertSame(0, $this->statusCount('archived'));
    }

    public function test_action_with_explicit_permission_is_checked_against_that_permission(): void
    {
        $this->deny([SecretAction::PERMISSION]);

        $this->postAction('secret', [], 1)->assertForbidden();

        self::assertSame([], $this->probe->calls);
    }

    public function test_action_with_explicit_permission_ignores_the_default_ability(): void
    {
        $this->deny(['action.secret']);

        $this->postAction('secret', [], 1)->assertOk();

        self::assertSame('secret', $this->probe->calls[0]['action']);
    }

    public function test_scope_not_declared_by_the_action_returns_422_on_the_bulk_route(): void
    {
        $this->postAction('row_only', ['keys' => [1, 2]])->assertStatus(422);

        self::assertSame([], $this->probe->calls);
    }

    public function test_scope_not_declared_by_the_action_returns_422_on_the_entity_route(): void
    {
        $this->postAction('row_only')->assertStatus(422);

        self::assertSame([], $this->probe->calls);
    }

    public function test_scope_declared_by_the_action_is_accepted_on_the_row_route(): void
    {
        $this->postAction('row_only', [], 1)->assertOk();

        self::assertSame('row', $this->probe->calls[0]['scope']);
    }

    public function test_missing_required_input_returns_422_with_messages_and_runs_nothing(): void
    {
        $response = $this->postAction('rename', ['input' => ['title' => '']]);

        $response->assertStatus(422);
        $errors = (array) $response->json('errors');
        self::assertNotSame([], $errors);
        $titleKeys = array_filter(array_keys($errors), static fn(int|string $key): bool => str_contains((string) $key, 'title'));
        self::assertNotSame([], $titleKeys, 'The error bag must point at the invalid "title" input.');
        self::assertSame([], $this->probe->calls);
    }

    // ---- A37: rollback + event after commit ----------------------------------------------------

    public function test_action_that_throws_rolls_back_its_writes_and_returns_an_error_status(): void
    {
        $response = $this->postAction('explode');

        self::assertSame(['explode'], array_column($this->probe->calls, 'action'), 'handle() must have run (and failed).');
        self::assertGreaterThanOrEqual(400, $response->baseResponse->getStatusCode());
        self::assertSame(3, $this->statusCount('new'));
        self::assertSame(0, $this->statusCount('exploded'));
    }

    public function test_action_that_throws_dispatches_no_action_executed_event(): void
    {
        $this->postAction('explode');

        self::assertSame(['explode'], array_column($this->probe->calls, 'action'), 'handle() must have run (and failed).');
        self::assertSame(0, $this->seenCount(ActionExecuted::class));
    }

    public function test_successful_action_dispatches_action_executed_once_after_commit(): void
    {
        $this->postAction('archive', ['keys' => [1, 2]])->assertOk();

        self::assertSame(1, $this->seenCount(ActionExecuted::class));
        self::assertTrue($this->allSeenAfterCommit(), 'ActionExecuted must be delivered at transaction level 0.');
        self::assertSame([2], $this->archivedWhenDelivered, 'The action writes must already be committed at delivery.');
    }

    public function test_entity_scope_action_executed_event_uses_the_entity_key_sentinel(): void
    {
        $this->postAction('archive')->assertOk();

        self::assertSame([ActionExecuted::ENTITY_SCOPE_KEY], $this->seenKeys(ActionExecuted::class));
    }

    public function test_denied_action_dispatches_no_event(): void
    {
        $this->deny(['action.archive']);

        $this->postAction('archive', [], 1);

        self::assertSame([], $this->seenEvents);
    }
}
