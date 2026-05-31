<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use League\Flysystem\FilesystemException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Streams a storage file as a download attachment.
 *
 * Defense in depth:
 *   1. The route requires a valid signature (see {@see \BlackParadise\LaravelAdmin\Support\BPAdminFileUrl}).
 *      This prevents users from guessing arbitrary paths even within the allowed disk.
 *   2. Path traversal sequences ('..', null byte, leading '/', URL-encoded variants)
 *      are rejected.
 *   3. The disk must appear in `config('bpadmin.allowed_download_disks')`.
 */
final class SafeFileDownloadController
{
    public function download(Request $request, string $disk, string $path): Response
    {
        if (!$request->hasValidSignature()) {
            abort(403);
        }

        // Normalise URL-encoded traversal variants before checking. We intentionally
        // decode only the dot/backslash escapes — full urldecode could mask other
        // attack patterns. Leading '/' indicates an absolute path which is never valid.
        $normalized = str_replace(['\\', '%2e', '%2E'], ['/', '.', '.'], $path);
        if (
            str_contains($normalized, '..')
            || str_contains($normalized, "\0")
            || str_starts_with($normalized, '/')
        ) {
            throw new NotFoundHttpException();
        }

        $allowedDisks = config('bpadmin.allowed_download_disks', ['public']);
        if (!in_array($disk, $allowedDisks, true)) {
            throw new NotFoundHttpException();
        }

        try {
            $storage = Storage::disk($disk);
        } catch (InvalidArgumentException) {
            throw new NotFoundHttpException();
        }

        try {
            if (!$storage->exists($path)) {
                throw new NotFoundHttpException();
            }

            return $storage->download($path);
        } catch (NotFoundHttpException $e) {
            throw $e;
        } catch (FilesystemException|RuntimeException) {
            throw new NotFoundHttpException();
        }
    }
}
