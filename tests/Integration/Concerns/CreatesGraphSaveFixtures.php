<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Concerns;

use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\GraphSave\GsHostDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\GraphSave\GsItemDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\GraphSave\GsNoteDefinition;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/** Host (gs_hosts) + hasMany items + hasOne note on a faked `public` disk; permissive auth + logged-in admin. */
trait CreatesGraphSaveFixtures
{
    use ActsAsPermissiveAdmin;

    protected function setUpGraphSaveFixtures(): void
    {
        Storage::fake('public');
        $this->stubHasher();

        Schema::create('gs_hosts', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('attachment')->nullable();
            $t->timestamps();
        });
        Schema::create('gs_items', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('host_id');
            $t->string('title');
            $t->timestamps();
        });
        Schema::create('gs_notes', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('host_id');
            $t->string('body'); // NOT NULL
            $t->timestamps();
        });

        $registry = app()->make(EntityDefinitionRegistry::class);
        $registry->register(new GsHostDefinition());
        $registry->register(new GsItemDefinition());
        $registry->register(new GsNoteDefinition());

        $this->permitEverything();
        $this->loginAdmin();
    }

    /** @return list<string> */
    protected function publicFiles(): array
    {
        $files = Storage::disk('public')->allFiles();
        sort($files);

        return $files;
    }
}
