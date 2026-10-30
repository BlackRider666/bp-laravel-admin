<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Feature\Console;

use BlackParadise\CoreAdmin\Domain\Exceptions\InvalidDefinitionException;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\Feature\Console\Fixtures\Doctor\DoctorCleanItem;
use BlackParadise\LaravelAdmin\Tests\Feature\Console\Fixtures\Doctor\DoctorHostWithUnregisteredChild;
use BlackParadise\LaravelAdmin\Tests\Feature\Console\Fixtures\Doctor\DoctorMissingActionClass;
use BlackParadise\LaravelAdmin\Tests\Feature\Console\Fixtures\Doctor\DoctorNonActionClass;
use BlackParadise\LaravelAdmin\Tests\Feature\Console\Fixtures\Doctor\DoctorSearchableRelation;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\ActsAsPermissiveAdmin;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\Actions\ActionNote;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ModelLess\BootEmbeddedChild\ChildSource;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ModelLess\BootEmbeddedChild\HostWithEmbeddedChild;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ModelLess\BootRelationTarget\LookupSource;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ModelLess\BootRelationTarget\OrderWithLookup;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ModelLess\BootWritable\WritableNoMutator;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use stdClass;

/**
 * Task 6.1 (RED) - A33/A34/A43/A45/A56: `bpadmin:doctor` lists every boot-validation violation of the
 * registered definitions (table or --json `[{definition, subject, rule, message}]`), exit 0 when clean,
 * exit 1 on any violation. Definitions are registered straight into the registry (boot() would throw).
 *
 * Pinned rule names: model-less-writable, model-less-relation-target, model-less-embedded-child,
 * non-queryable-field, unregistered-embedded, unknown-action.
 * Pinned runtime contract (review I-1): executing an action on a definition whose actions() holds an
 * unknown / non-ActionContract class throws InvalidDefinitionException naming definition and class.
 */
final class DoctorCommandTest extends TestCase
{
    use ActsAsPermissiveAdmin;

    /** @param list<EntityDefinition> $definitions */
    private function register(array $definitions): void
    {
        $registry = resolve(EntityDefinitionRegistry::class);
        foreach ($definitions as $definition) {
            $registry->register($definition);
        }
    }

    /** @return list<array{definition: string, subject: string, rule: string, message: string}> */
    private function jsonViolations(): array
    {
        $exit = Artisan::call('bpadmin:doctor', ['--json' => true]);
        self::assertSame(1, $exit, 'doctor must exit 1 when violations exist');

        /** @var list<array{definition: string, subject: string, rule: string, message: string}> $decoded */
        $decoded = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /**
     * @param list<array{definition: string, subject: string, rule: string, message: string}> $violations
     * @return array{definition: string, subject: string, rule: string, message: string}|null
     */
    private function find(array $violations, string $rule, string $definition): ?array
    {
        foreach ($violations as $violation) {
            if ($violation['rule'] === $rule && $violation['definition'] === $definition) {
                return $violation;
            }
        }

        return null;
    }

    // ---- A45/A56: clean ---------------------------------------------------------------------

    public function test_doctor_on_clean_registry_exits_zero_and_reports_no_violations(): void
    {
        $this->register([new DoctorCleanItem()]);

        $exit = Artisan::call('bpadmin:doctor');

        self::assertSame(0, $exit);
        self::assertStringContainsString('no violations', Artisan::output());
    }

    public function test_doctor_json_on_clean_registry_prints_empty_list_and_exits_zero(): void
    {
        $this->register([new DoctorCleanItem()]);

        $exit = Artisan::call('bpadmin:doctor', ['--json' => true]);

        self::assertSame(0, $exit);
        self::assertSame([], json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR));
    }

    // ---- A33: model-less writable -----------------------------------------------------------

    public function test_doctor_reports_writable_model_less_definition_without_mutator(): void
    {
        $this->register([new WritableNoMutator()]);

        $violation = $this->find($this->jsonViolations(), 'model-less-writable', 'writable_no_mutator');

        self::assertNotNull($violation);
        self::assertMatchesRegularExpression('/mutator/i', $violation['message']);
    }

    // ---- A34: model-less relation target / embedded child -----------------------------------

    public function test_doctor_reports_model_less_relation_target_naming_definition_and_field(): void
    {
        $this->register([new LookupSource(), new OrderWithLookup()]);

        $violation = $this->find($this->jsonViolations(), 'model-less-relation-target', 'order_with_lookup');

        self::assertNotNull($violation);
        self::assertNotSame('', $violation['subject']);
        self::assertStringContainsString('lookup_source', $violation['message']);
    }

    public function test_doctor_reports_model_less_embedded_child(): void
    {
        $this->register([new ChildSource(), new HostWithEmbeddedChild()]);

        $violation = $this->find($this->jsonViolations(), 'model-less-embedded-child', 'host_with_embedded_child');

        self::assertNotNull($violation);
        self::assertStringContainsString('child_source', $violation['message']);
    }

    // ---- A43: non-queryable searchable, unregistered embedded -------------------------------

    public function test_doctor_reports_non_queryable_searchable_field_with_field_as_subject(): void
    {
        $this->register([new DoctorSearchableRelation()]);

        $violation = $this->find($this->jsonViolations(), 'non-queryable-field', 'doctor_searchable_relation');

        self::assertNotNull($violation);
        self::assertSame('comments', $violation['subject']);
    }

    public function test_doctor_reports_unregistered_embedded_definition_naming_the_class(): void
    {
        $this->register([new DoctorHostWithUnregisteredChild()]);

        $violation = $this->find($this->jsonViolations(), 'unregistered-embedded', 'doctor_host_with_unregistered_child');

        self::assertNotNull($violation);
        self::assertSame('child_id', $violation['subject']);
        self::assertStringContainsString('DoctorUnregisteredChild', $violation['message']);
    }

    // ---- I-1: unknown action classes --------------------------------------------------------

    public function test_doctor_reports_action_class_that_does_not_exist(): void
    {
        $this->register([new DoctorMissingActionClass()]);

        $violation = $this->find($this->jsonViolations(), 'unknown-action', 'doctor_missing_action_class');

        self::assertNotNull($violation);
        self::assertSame(DoctorMissingActionClass::MISSING, $violation['subject']);
        self::assertStringContainsString(DoctorMissingActionClass::MISSING, $violation['message']);
    }

    public function test_doctor_reports_action_class_that_is_not_an_action_contract(): void
    {
        $this->register([new DoctorNonActionClass()]);

        $violation = $this->find($this->jsonViolations(), 'unknown-action', 'doctor_non_action_class');

        self::assertNotNull($violation);
        self::assertSame(stdClass::class, $violation['subject']);
    }

    // ---- A56: output shape, table, exit code ------------------------------------------------

    public function test_doctor_json_entries_have_exactly_the_four_documented_keys(): void
    {
        $this->register([new WritableNoMutator()]);

        $violations = $this->jsonViolations();

        self::assertNotSame([], $violations);
        $keys = array_keys($violations[0]);
        sort($keys);
        self::assertSame(['definition', 'message', 'rule', 'subject'], $keys);
    }

    public function test_doctor_json_lists_every_violation_across_definitions(): void
    {
        $this->register([new WritableNoMutator(), new DoctorSearchableRelation(), new DoctorMissingActionClass()]);

        $rules = array_column($this->jsonViolations(), 'rule');

        self::assertContains('model-less-writable', $rules);
        self::assertContains('non-queryable-field', $rules);
        self::assertContains('unknown-action', $rules);
    }

    public function test_doctor_table_output_lists_violation_and_exits_one(): void
    {
        $this->register([new WritableNoMutator()]);

        $exit = Artisan::call('bpadmin:doctor');

        self::assertSame(1, $exit);
        self::assertStringContainsString('model-less-writable', Artisan::output());
    }

    // ---- I-1: runtime exception -------------------------------------------------------------

    public function test_executing_action_with_missing_class_throws_domain_exception_naming_definition_and_class(): void
    {
        Schema::create('action_notes', function (Blueprint $t): void {
            $t->id();
            $t->string('title');
            $t->string('status')->default('new');
            $t->timestamps();
        });
        ActionNote::create(['title' => 'One']);
        $this->stubHasher();
        $this->register([new DoctorMissingActionClass()]);
        $this->permitEverything();
        $this->loginAdmin();
        $this->withoutExceptionHandling();

        try {
            $this->postJson(route('bpadmin.entity.action', [
                'entity' => 'doctor_missing_action_class',
                'action' => 'anything',
            ]));
            self::fail('Expected InvalidDefinitionException');
        } catch (InvalidDefinitionException $e) {
            self::assertStringContainsString('doctor_missing_action_class', $e->getMessage());
            self::assertStringContainsString(DoctorMissingActionClass::MISSING, $e->getMessage());
        }
    }
}
