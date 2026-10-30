<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Feature\Http;

use BlackParadise\CoreAdmin\Domain\Fields\HashedField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\ActsAsPermissiveAdmin;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItem;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class PasswordKeepDefinition extends EntityDefinition
{
    public string $model = TestItem::class;

    public function resolveName(): string
    {
        return 'password_keep';
    }

    public function fields(): array
    {
        return [TextField::make('name'), HashedField::make('password')];
    }
}

/** Task 2.1 — A15: updating with an empty password field keeps the existing hash. */
final class PasswordKeepTest extends TestCase
{
    use RefreshDatabase;
    use ActsAsPermissiveAdmin;

    private int $id;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stubHasher();
        Schema::create('test_items', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('email')->nullable();
            $t->string('password')->nullable();
            $t->unsignedBigInteger('category_id')->nullable();
            $t->timestamps();
        });
        $this->permitEverything();
        app()->make(EntityDefinitionRegistry::class)->register(new PasswordKeepDefinition());
        $this->loginAdmin();

        $this->postJson(route('bpadmin.entity.store', ['entity' => 'password_keep']), ['name' => 'U', 'password' => 'secret'])->assertCreated();
        $this->id = (int) DB::table('test_items')->value('id');
    }

    private function hash(): mixed
    {
        return DB::table('test_items')->where('id', $this->id)->value('password');
    }

    public function test_update_with_empty_password_keeps_existing_hash(): void
    {
        $this->putJson(route('bpadmin.entity.update', ['entity' => 'password_keep', 'id' => $this->id]), ['name' => 'U2', 'password' => ''])->assertOk();

        self::assertSame('stub_hashed_secret', $this->hash());
        $this->assertDatabaseHas('test_items', ['id' => $this->id, 'name' => 'U2']);
    }

    public function test_update_with_null_password_keeps_existing_hash(): void
    {
        $this->putJson(route('bpadmin.entity.update', ['entity' => 'password_keep', 'id' => $this->id]), ['name' => 'U', 'password' => null])->assertOk();

        self::assertSame('stub_hashed_secret', $this->hash());
    }

    public function test_update_without_password_key_keeps_existing_hash(): void
    {
        $this->putJson(route('bpadmin.entity.update', ['entity' => 'password_keep', 'id' => $this->id]), ['name' => 'U3'])->assertOk();

        self::assertSame('stub_hashed_secret', $this->hash());
    }

    public function test_update_with_new_password_rehashes(): void
    {
        $this->putJson(route('bpadmin.entity.update', ['entity' => 'password_keep', 'id' => $this->id]), ['name' => 'U', 'password' => 'fresh'])->assertOk();

        self::assertSame('stub_hashed_fresh', $this->hash());
    }
}
