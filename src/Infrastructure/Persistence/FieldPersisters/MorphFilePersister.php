<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Persistence\FieldPersisters;

use BlackParadise\CoreAdmin\Domain\Contracts\Fields\FieldContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Files\FileStorageProviderContract;
use BlackParadise\CoreAdmin\Domain\Fields\MorphFileField;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

/**
 * morph_file: one file-model row per field (`type` = storesAs) attached through a
 * morphMany relation on the host.
 *
 *  - new upload   → old rows removed, new row created (new file wins over `__clear`);
 *  - `__clear`    → rows removed, files removed after commit (optional fields only);
 *  - other values → existing rows kept (upload-only persister).
 *
 * Disk I/O for removals is deferred via {@see \BlackParadise\LaravelAdmin\Support\DeferredFileOperations}.
 */
final class MorphFilePersister extends AbstractFieldPersister
{
    public function __construct(private readonly FileStorageProviderContract $fileStorage) {}

    public function afterSave(FieldContract $field, mixed $value, Model $host, PersistContext $context): void
    {
        if (!$field instanceof MorphFileField) {
            return;
        }

        if ($value instanceof UploadedFile) {
            if (!$context->isCreate()) {
                $this->removeRows($host, $field, $context);
            }
            $this->attach($host, $field, $value, $context);

            return;
        }

        if (!$context->isCreate() && $this->isClearRequested($field, $context)) {
            $this->removeRows($host, $field, $context);
        }
    }

    public function beforeDelete(FieldContract $field, Model $host, PersistContext $context): void
    {
        if (!$field instanceof MorphFileField || !$context->cascades()) {
            return;
        }
        $this->removeRows($host, $field, $context);
    }

    private function attach(Model $host, MorphFileField $field, UploadedFile $file, PersistContext $context): void
    {
        $dir  = $field->getDirectory() !== '' ? $field->getDirectory() : $field->name();
        $disk = $this->diskOf($field);
        $path = $this->fileStorage->store($dir, $file, $disk);
        $context->files->recordUpload($path, $disk);

        $host->{$field->getMorphName()}()->create([
            'type'      => $field->getStoresAs(),
            'name'      => $file->getClientOriginalName(),
            'path'      => $path,
            'mime_type' => $file->getClientMimeType(),
            'size'      => $file->getSize(),
        ]);
    }

    /** Delete this field's rows now; record their files for removal after commit. */
    private function removeRows(Model $host, MorphFileField $field, PersistContext $context): void
    {
        $disk    = $this->diskOf($field);
        $records = (clone $host->{$field->getMorphName()}())->where('type', $field->getStoresAs())->get();
        foreach ($records as $record) {
            $path = $record->getAttribute('path');
            if (is_string($path) && $path !== '') {
                $context->files->recordDeletion($path, $disk);
            }
            $record->delete();
        }
    }

    private function diskOf(MorphFileField $field): ?string
    {
        $disk = $field->getDisk();

        return $disk !== '' ? $disk : null;
    }
}
