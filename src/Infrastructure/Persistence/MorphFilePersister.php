<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Persistence;

use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Files\FileStorageProviderContract;
use BlackParadise\CoreAdmin\Domain\Fields\MorphFileField;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

/**
 * Handles the morph_file lifecycle for {@see EloquentEntityMutator}:
 *  - persisting newly uploaded files on create,
 *  - replacing existing morph file records on update,
 *  - collecting paths and deleting morph DB rows on delete (split so the
 *    mutator can perform disk I/O after the host transaction commits).
 *
 * Stateless — safe to bind as a singleton.
 */
final readonly class MorphFilePersister
{
    public function __construct(
        private FileStorageProviderContract $fileStorage,
    ) {}

    /**
     * Persist uploaded files as morph_file records attached to the host model.
     *
     * Returns the list of paths just written to disk so the caller (mutator)
     * can roll them back if the host transaction fails.
     *
     * @param array<string, mixed> $rawAttributes Original attributes (before filter).
     * @return list<array{path: string, disk: ?string}>
     */
    public function persistOnCreate(
        Model $host,
        EntityDefinitionContract $definition,
        array $rawAttributes,
    ): array {
        $uploaded = [];
        foreach ($definition->fields() as $field) {
            if (!$field instanceof MorphFileField) {
                continue;
            }
            $name = $field->name();
            if (!array_key_exists($name, $rawAttributes)) {
                continue;
            }
            $value = $rawAttributes[$name];
            if (!$value instanceof UploadedFile) {
                // Non-UploadedFile values (e.g. raw paths posted via JSON) are intentionally
                // skipped — this persister is upload-only. JSON / programmatic morph_file
                // imports must build MorphFile records directly via the file model.
                continue;
            }
            foreach ($this->attachMorphFile($host, $field, $value) as $newPath) {
                $uploaded[] = $newPath;
            }
        }
        return $uploaded;
    }

    /**
     * Apply update-side morph_file handling: replace records where a new upload
     * was supplied, leave others untouched.
     *
     * Returns two lists:
     *  - `uploaded`: paths written to disk during this call (rollback set on tx failure)
     *  - `deferredDeletes`: OLD paths to delete from disk AFTER tx commit
     *
     * The caller MUST delete `deferredDeletes` only after a successful commit;
     * deleting earlier would orphan the file when a rollback restores the
     * morph_file row.
     *
     * @param array<string, mixed> $rawAttributes
     * @return array{uploaded: list<array{path: string, disk: ?string}>, deferredDeletes: list<array{path: string, disk: ?string}>}
     */
    public function persistOnUpdate(
        Model $host,
        EntityDefinitionContract $definition,
        array $rawAttributes,
    ): array {
        $uploaded        = [];
        $deferredDeletes = [];

        foreach ($definition->fields() as $field) {
            if (!$field instanceof MorphFileField) {
                continue;
            }
            $name = $field->name();
            if (!array_key_exists($name, $rawAttributes)) {
                continue; // no mention → keep existing
            }
            $value = $rawAttributes[$name];
            if ($value instanceof UploadedFile) {
                $result = $this->replaceMorphFile($host, $field, $value);
                foreach ($result['uploaded'] as $u) {
                    $uploaded[] = $u;
                }
                foreach ($result['deferredDeletes'] as $d) {
                    $deferredDeletes[] = $d;
                }
            }
            // null/empty → leave existing (explicit delete is a future enhancement).
        }

        return ['uploaded' => $uploaded, 'deferredDeletes' => $deferredDeletes];
    }

    /**
     * Collect all disk paths stored in morph_file records attached to a host.
     * Does NOT delete any DB rows or disk files.
     *
     * @return list<array{path: string, disk: ?string}>
     */
    public function collectPathsForDelete(
        Model $host,
        EntityDefinitionContract $definition,
    ): array {
        $paths = [];
        foreach ($definition->fields() as $field) {
            if (!$field instanceof MorphFileField) {
                continue;
            }
            $morphName = $field->getMorphName();
            $records   = (clone $host->{$morphName}())->where('type', $field->getStoresAs())->get();

            $disk = $this->fieldDisk($field);
            foreach ($records as $record) {
                $path = $record->getAttribute('path');
                if (is_string($path) && $path !== '') {
                    $paths[] = ['path' => $path, 'disk' => $disk];
                }
            }
        }
        return $paths;
    }

    /**
     * Delete all morph_file DB rows attached to a host.
     * Does NOT touch the disk — call {@see collectPathsForDelete()} first.
     */
    public function deleteRecords(
        Model $host,
        EntityDefinitionContract $definition,
    ): void {
        foreach ($definition->fields() as $field) {
            if (!$field instanceof MorphFileField) {
                continue;
            }
            $morphName = $field->getMorphName();
            (clone $host->{$morphName}())->where('type', $field->getStoresAs())->delete();
        }
    }

    /**
     * Store the file on disk and create the morph record.
     *
     * @return list<array{path: string, disk: ?string}> A single-element list with the path just written.
     */
    private function attachMorphFile(
        Model $host,
        MorphFileField $field,
        UploadedFile $file,
    ): array {
        $dir  = $field->getDirectory() !== '' ? $field->getDirectory() : $field->name();
        $disk = $this->fieldDisk($field);
        $path = $this->fileStorage->store($dir, $file, $disk);

        $morphName = $field->getMorphName();
        $host->{$morphName}()->create([
            'type'      => $field->getStoresAs(),
            'name'      => $file->getClientOriginalName(),
            'path'      => $path,
            'mime_type' => $file->getClientMimeType(),
            'size'      => $file->getSize(),
        ]);

        return [['path' => $path, 'disk' => $disk]];
    }

    /**
     * Replace existing morph_file records for a given field with the new upload.
     *
     * Deletes the OLD morph DB rows inside the current transaction, but returns
     * their disk paths so the caller can defer the disk-delete until AFTER the
     * transaction commits.
     *
     * @return array{uploaded: list<array{path: string, disk: ?string}>, deferredDeletes: list<array{path: string, disk: ?string}>}
     */
    private function replaceMorphFile(
        Model $host,
        MorphFileField $field,
        UploadedFile $file,
    ): array {
        $morphName = $field->getMorphName();
        $existing  = (clone $host->{$morphName}())->where('type', $field->getStoresAs())->get();

        $disk = $this->fieldDisk($field);
        $deferredDeletes = [];
        foreach ($existing as $old) {
            $oldPath = $old->getAttribute('path');
            if (is_string($oldPath) && $oldPath !== '') {
                $deferredDeletes[] = ['path' => $oldPath, 'disk' => $disk];
            }
            $old->delete();
        }

        return [
            'uploaded'        => $this->attachMorphFile($host, $field, $file),
            'deferredDeletes' => $deferredDeletes,
        ];
    }

    private function fieldDisk(MorphFileField $field): ?string
    {
        $disk = $field->getDisk();
        return $disk !== '' ? $disk : null;
    }
}
