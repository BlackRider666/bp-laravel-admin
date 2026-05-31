<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Files;

use BlackParadise\CoreAdmin\Domain\Contracts\Files\FileStorageProviderContract;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Laravel implementation of {@see FileStorageProviderContract}.
 *
 * Stores files using a Laravel filesystem disk. When the caller passes an
 * explicit `$disk`, that disk is used; otherwise the disk configured via
 * `bpadmin.storage_disk` (default: 'public') is used.
 */
final class LaravelFileStorage implements FileStorageProviderContract
{
    /**
     * Store a file at the given path and return the stored path.
     *
     * @param string $path Directory path where the file will be stored.
     * @param mixed $file Must be an {@see UploadedFile} instance.
     * @param string|null $disk Optional disk name; falls back to configured default.
     * @throws InvalidArgumentException when $file is not an UploadedFile.
     */
    public function store(string $path, mixed $file, ?string $disk = null): string
    {
        if (!$file instanceof UploadedFile) {
            throw new InvalidArgumentException('Expected an UploadedFile instance.');
        }

        return (string) $file->store($path, $this->resolveDisk($disk));
    }

    /**
     * Delete a file by its stored path.
     */
    public function delete(string $path, ?string $disk = null): bool
    {
        return Storage::disk($this->resolveDisk($disk))->delete($path);
    }

    /**
     * Get the public URL for a stored file path.
     */
    public function url(string $path, ?string $disk = null): string
    {
        return Storage::disk($this->resolveDisk($disk))->url($path);
    }

    private function resolveDisk(?string $disk): string
    {
        if ($disk !== null && $disk !== '') {
            return $disk;
        }

        return (string) config('bpadmin.storage_disk', 'public');
    }
}
