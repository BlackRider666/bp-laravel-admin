<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Feature\Http;

use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\Http\Presenters\EntityPresenterInterface;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\ActsAsPermissiveAdmin;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\Actions\ActionNote;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\Actions\ActionProbe;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\Actions\RatedNoteDefinition;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mockery\Expectation;
use Mockery\MockInterface;

/**
 * Smoke S-2: action-form field errors are keyed by the Laravel dot path of the posted form name
 * (`input[rating]` => `input.rating`) so the UI can reopen the modal and show them.
 * Non-field errors keep their own keys. Convention is identical for JSON and HTML presenters.
 */
final class ActionInputValidationKeysTest extends TestCase
{
    use ActsAsPermissiveAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stubHasher();
        app()->singleton(ActionProbe::class);

        Schema::create('action_notes', function (Blueprint $t): void {
            $t->id();
            $t->string('title');
            $t->string('status')->default('new');
            $t->timestamps();
        });
        ActionNote::create(['title' => 'One']);

        resolve(EntityDefinitionRegistry::class)->register(new RatedNoteDefinition());
        $this->permitEverything();
        $this->loginAdmin();
    }

    private function rateUrl(): string
    {
        return route('bpadmin.entity.action.row', ['entity' => 'rated_note', 'id' => 1, 'action' => 'rate']);
    }

    public function test_json_out_of_range_input_returns_422_with_errors_keyed_input_dot_field(): void
    {
        $response = $this->postJson($this->rateUrl(), ['input' => ['rating' => 9]]);

        $response->assertStatus(422);
        $errors = (array) $response->json('errors');
        self::assertArrayHasKey('input.rating', $errors);
        self::assertArrayNotHasKey('rating', $errors);
    }

    public function test_json_missing_required_input_returns_422_keyed_input_dot_field(): void
    {
        $response = $this->postJson($this->rateUrl(), ['input' => ['rating' => '']]);

        $response->assertStatus(422);
        self::assertArrayHasKey('input.rating', (array) $response->json('errors'));
    }

    public function test_presenter_receives_input_dot_field_keys_and_input_is_flashed_for_html_request(): void
    {
        $this->mock(EntityPresenterInterface::class, static function (MockInterface $mock): void {
            /** @var Expectation $expectation */
            $expectation = $mock->shouldReceive('validationError');
            $expectation->once()->andReturnUsing(
                static fn(array $errors) => back()->withErrors($errors)->withInput(),
            );
        });

        $response = $this->from('/admin/rated_note')->post($this->rateUrl(), ['input' => ['rating' => 9]]);

        $response->assertSessionHasErrors('input.rating');
        $response->assertSessionDoesntHaveErrors('rating');
        $response->assertSessionHasInput('input.rating', '9');
    }

    public function test_non_field_scope_error_keeps_its_action_key(): void
    {
        $url = route('bpadmin.entity.action', ['entity' => 'rated_note', 'action' => 'rate']);

        $response = $this->postJson($url, ['input' => ['rating' => 3]]);

        $response->assertStatus(422);
        $errors = (array) $response->json('errors');
        self::assertArrayHasKey('action', $errors);
        self::assertArrayNotHasKey('input.action', $errors);
    }
}
