<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Infrastructure;

use BlackParadise\CoreAdmin\Domain\Contracts\Auth\AuthorizationProviderContract;
use BlackParadise\CoreAdmin\Domain\Entity\EntityRecord;
use BlackParadise\CoreAdmin\Domain\Fields\HashedField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\CoreAdmin\Domain\Mutators\EntityMutatorInterface;
use BlackParadise\CoreAdmin\Domain\ValueObjects\EntityKey;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\StubsValueHasher;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItem;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Mockery;

/**
 * A13 (B11): The mutator must not re-hash an already-hashed value on a hashed field.
 *
 * Bug: when a form round-trips the hashed value (e.g. for display), submitting it
 * back causes the mutator to call $hasher->hash($alreadyHashed), producing a
 * double-hashed string that no longer verifies against the original plain-text password.
 *
 * Fix: in filterAttributes, check isHashed() before hashing; skip if already hashed.
 */
final class MutatorHashedFieldGuardTest extends TestCase
{
    use RefreshDatabase;
    use StubsValueHasher;

    private EntityMutatorInterface $mutator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stubValueHasher(); // must be first — prevents LaravelValueHasher fatal

        Schema::create('test_items', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('password')->nullable();
            $t->timestamps();
        });

        $this->app->bind(AuthorizationProviderContract::class, function (): AuthorizationProviderContract {
            $mock = Mockery::mock(AuthorizationProviderContract::class);
            $mock->shouldReceive('can')->andReturn(true);
            return $mock;
        });

        $this->mutator = $this->app->make(EntityMutatorInterface::class);
    }

    private function makeDefinition(): EntityDefinition
    {
        return new class extends EntityDefinition {
            public string $model = TestItem::class;

            public function resolveName(): string
            {
                return 'test_item_hashed';
            }

            public function fields(): array
            {
                return [
                    TextField::make('name'),
                    HashedField::make('password'),
                ];
            }
        };
    }

    // ------------------------------------------------------------------
    // A13 — submitting an already-hashed value must not re-hash it
    // ------------------------------------------------------------------

    /**
     * When the form round-trips an already-hashed value and submits it back,
     * the mutator must store the value AS-IS (no double-hash).
     *
     * With StubsValueHasher: hash('plain') = 'stub_hashed_plain', isHashed('stub_hashed_plain') = true.
     * So submitting back 'stub_hashed_plain' must store it unchanged.
     *
     * Currently FAILS: filterAttributes always calls $hasher->hash($value) regardless,
     * so 'stub_hashed_plain' gets re-hashed to 'stub_hashed_stub_hashed_plain'.
     */
    public function test_already_hashed_value_is_not_re_hashed_on_update(): void
    {
        $definition = $this->makeDefinition();
        $originalHash = 'stub_hashed_secret'; // pre-hashed value (stubbed)

        // Create record with the hashed value directly (bypass normal create flow by
        // forcing the already-hashed value).
        $item = TestItem::create([
            'name'     => 'Alice',
            'password' => $originalHash,
        ]);

        // Now update, submitting the same already-hashed value back
        $this->mutator->update(
            new EntityKey($item->id, 'int'),
            new EntityRecord($definition, [
                'name'     => 'Alice',
                'password' => $originalHash, // pass the hash back
            ]),
        );

        $stored = TestItem::find($item->id)->password;

        // After update, stored value must be the same hash, not double-hashed.
        // Currently FAILS: hash gets re-hashed → 'stub_hashed_stub_hashed_secret'.
        $this->assertSame(
            $originalHash,
            $stored,
            'Already-hashed value must be stored as-is, not re-hashed',
        );
    }

    /**
     * Normal flow: submitting plain text must be hashed on create.
     */
    public function test_plain_text_password_is_hashed_on_create(): void
    {
        $definition = $this->makeDefinition();

        $created = $this->mutator->create(new EntityRecord($definition, [
            'name'     => 'Bob',
            'password' => 'mypassword',
        ]));

        $stored = TestItem::find($created->id())->password;
        // With StubsValueHasher: hash('mypassword') = 'stub_hashed_mypassword'
        $this->assertSame('stub_hashed_mypassword', $stored);
    }
}
