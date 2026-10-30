<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Concerns;

use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\OwnedCascade\OcChild;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\OwnedCascade\OcChildDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\OwnedCascade\OcHost;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\OwnedCascade\OcHostDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\OwnedCascade\OcNoteDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\OwnedCascade\OcSoftHost;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\OwnedCascade\OcSoftHostDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestTag;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Hosts with OWNED embedded hasMany/morphMany children, a child file (disk `archive`) and a child
 * belongsToMany pivot. No foreign keys anywhere (the WRP shape): only BPAdmin can cascade.
 */
trait CreatesOwnedCascadeFixtures
{
    use ActsAsPermissiveAdmin;

    protected TestTag $tagA;
    protected TestTag $tagB;

    protected function setUpOwnedCascadeFixtures(): void
    {
        Storage::fake('archive');
        $this->stubHasher();

        Schema::create('test_tags', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->timestamps();
        });
        Schema::create('oc_hosts', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->timestamps();
        });
        Schema::create('oc_soft_hosts', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->softDeletes();
            $t->timestamps();
        });
        Schema::create('oc_children', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('host_id')->nullable();
            $t->unsignedBigInteger('soft_host_id')->nullable();
            $t->string('title');
            $t->string('doc')->nullable();
            $t->timestamps();
        });
        Schema::create('oc_notes', function (Blueprint $t): void {
            $t->id();
            $t->string('notable_type');
            $t->unsignedBigInteger('notable_id');
            $t->string('text');
            $t->string('attachment')->nullable();
            $t->timestamps();
        });
        Schema::create('oc_child_tag', function (Blueprint $t): void {
            $t->unsignedBigInteger('oc_child_id');
            $t->unsignedBigInteger('test_tag_id');
        });

        $registry = app()->make(EntityDefinitionRegistry::class);
        $registry->register(new OcHostDefinition());
        $registry->register(new OcSoftHostDefinition());
        $registry->register(new OcChildDefinition());
        $registry->register(new OcNoteDefinition());

        $this->tagA = TestTag::create(['name' => 'A']);
        $this->tagB = TestTag::create(['name' => 'B']);

        $this->permitEverything();
        $this->loginAdmin();
    }

    /**
     * @param array<string, int> $owner foreign key column => host id
     * @param list<int> $tagIds
     */
    protected function seedChild(array $owner, string $title, string $path, array $tagIds): OcChild
    {
        Storage::disk('archive')->put($path, 'file');
        $child = OcChild::create([...$owner, 'title' => $title, 'doc' => $path]);
        $child->tags()->attach($tagIds);

        return $child;
    }

    /** Host with two children (3 pivot rows), one morph note and a stranger host with its own child + pivot. */
    protected function seedPlainHostGraph(): OcHost
    {
        $host = OcHost::create(['name' => 'Plain']);
        $this->seedChild(['host_id' => $host->id], 'Child Alpha', 'oc/alpha.bin', [$this->tagA->id, $this->tagB->id]);
        $this->seedChild(['host_id' => $host->id], 'Child Beta', 'oc/beta.bin', [$this->tagA->id]);
        Storage::disk('archive')->put('oc/note.bin', 'file');
        $host->notes()->create(['text' => 'Note', 'attachment' => 'oc/note.bin']);

        $other = OcHost::create(['name' => 'Stranger']);
        $this->seedChild(['host_id' => $other->id], 'Stranger child', 'oc/stranger.bin', [$this->tagA->id]);

        return $host;
    }

    /** Soft-deletable host with two children (3 pivot rows). */
    protected function seedSoftHostGraph(): OcSoftHost
    {
        $host = OcSoftHost::create(['name' => 'Soft']);
        $this->seedChild(['soft_host_id' => $host->id], 'Child Alpha', 'oc/alpha.bin', [$this->tagA->id, $this->tagB->id]);
        $this->seedChild(['soft_host_id' => $host->id], 'Child Beta', 'oc/beta.bin', [$this->tagA->id]);

        return $host;
    }

    protected function pivotRows(): int
    {
        return DB::table('oc_child_tag')->count();
    }
}
