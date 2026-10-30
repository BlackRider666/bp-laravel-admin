<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Persistence\FieldPersisters;

use BlackParadise\CoreAdmin\Domain\Contracts\Fields\FieldContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Files\FileStorageProviderContract;
use BlackParadise\CoreAdmin\Domain\Fields\FileField;
use BlackParadise\CoreAdmin\Domain\Fields\ImageField;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

/**
 * file / image field stored as a path in a host column.
 *
 *  - new upload   → stored, old file removed after commit (new file wins over `__clear`);
 *  - `__clear`    → column set to null, old file removed after commit (optional fields only);
 *  - empty value  → stored file is kept.
 *
 * All removals are only RECORDED; DeferredFileOperations deletes them after the
 * outermost commit and discards them on rollback.
 */
final class ColumnFilePersister extends AbstractFieldPersister
{
    public function __construct(private readonly FileStorageProviderContract $fileStorage) {}

    public function toAttributes(FieldContract $field, mixed $value, PersistContext $context): array
    {
        $name = $field->name();
        $disk = $this->diskOf($field);
        $old  = $context->existing?->getAttribute($name);
        $old  = is_string($old) && $old !== '' ? $old : null;

        if ($value instanceof UploadedFile) {
            $path = $this->fileStorage->store($this->directoryOf($field, $context), $value, $disk);
            $context->files->recordUpload($path, $disk);
            if ($old !== null && $old !== $path) {
                $context->files->recordDeletion($old, $disk);
            }

            return [$name => $path];
        }

        if ($value === null || $value === '') {
            if ($old !== null && !$context->isCreate() && $this->isClearRequested($field, $context)) {
                $context->files->recordDeletion($old, $disk);

                return [$name => null];
            }

            return [];
        }

        if ($old !== null && $value !== $old) {
            $context->files->recordDeletion($old, $disk);
        }

        return [$name => $value];
    }

    public function beforeDelete(FieldContract $field, Model $host, PersistContext $context): void
    {
        if (!$context->cascades()) {
            return;
        }
        $path = $host->getAttribute($field->name());
        if (is_string($path) && $path !== '') {
            $context->files->recordDeletion($path, $this->diskOf($field));
        }
    }

    private function directoryOf(FieldContract $field, PersistContext $context): string
    {
        if (($field instanceof FileField || $field instanceof ImageField) && $field->getDirectory() !== '') {
            return $field->getDirectory();
        }

        return $context->definition->name() . '/' . $field->name();
    }

    private function diskOf(FieldContract $field): ?string
    {
        if ($field instanceof FileField || $field instanceof ImageField) {
            $disk = $field->getDisk();

            return $disk !== '' ? $disk : null;
        }

        return null;
    }
}
