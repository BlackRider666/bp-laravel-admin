<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Support;

use Illuminate\Support\Facades\URL;

/**
 * Helper for generating signed download URLs.
 *
 * SafeFileDownloadController requires `hasValidSignature()` to succeed.
 * Views / presenters must obtain download URLs through this helper so the
 * link carries a valid signature with a short TTL.
 */
final class BPAdminFileUrl
{
    /**
     * Build a temporary signed download URL for the given disk + path.
     *
     * @param string $disk Storage disk name (must be in `bpadmin.allowed_download_disks`).
     * @param string $path Path on the disk, relative to the disk root.
     * @param int $minutes Link lifetime in minutes (default 15).
     */
    public static function signed(string $disk, string $path, int $minutes = 15): string
    {
        return URL::temporarySignedRoute(
            'bpadmin.files.download',
            now()->addMinutes($minutes),
            ['disk' => $disk, 'path' => $path],
        );
    }
}
