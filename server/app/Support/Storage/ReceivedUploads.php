<?php

declare(strict_types=1);

namespace App\Support\Storage;

use App\Exceptions\Refusal;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * An upload's `complete` (contract/api.md, "Uploads"): the file at the presigned key is copied, on
 * the bucket, to a key no upload link points at, and only that copy is checked (its size, then its
 * SHA-256) and processed. A presigned PUT can be sent again, and of any size, until it expires
 * (S3 doesn't sign Content-Length), so what's at the upload key can change after it's checked; the
 * copy can't. One complete at a time per upload: another at the same moment is told to wait.
 */
final class ReceivedUploads
{
    /**
     * The checked copy's key (in $folder), or null while another complete is checking it. The
     * upload key is deleted either way; a wrong file is refused (`409 upload_incomplete`, or
     * `upload_mismatch` for the wrong SHA-256) and nothing is kept.
     */
    public function receive(string $uploadKey, int $bytes, string $sha256, string $folder = 'uploads/received'): ?string
    {
        $lock = Cache::lock('upload:'.$uploadKey, 300);

        if (! $lock->get()) {
            return null;
        }

        try {
            return $this->check($uploadKey, $bytes, $sha256, $folder);
        } finally {
            $lock->release();
        }
    }

    private function check(string $uploadKey, int $bytes, string $sha256, string $folder): string
    {
        $disk = Storage::disk('private');

        if (! $disk->exists($uploadKey) || $disk->size($uploadKey) !== $bytes) {
            $disk->delete($uploadKey);

            throw new Refusal(409, 'upload_incomplete');
        }

        $extension = pathinfo($uploadKey, PATHINFO_EXTENSION);
        $copy = trim($folder, '/').'/'.Str::lower(Str::random(32)).($extension !== '' ? '.'.$extension : '');

        try {
            $disk->copy($uploadKey, $copy);
        } catch (Throwable $e) {
            // Too big to copy in one go (only a file swapped after the first check is).
            report($e);
            $disk->delete([$uploadKey, $copy]);

            throw new Refusal(409, 'upload_incomplete');
        }

        $disk->delete($uploadKey);

        if (! $disk->exists($copy) || $disk->size($copy) !== $bytes) {
            $disk->delete($copy);

            throw new Refusal(409, 'upload_incomplete');
        }

        $stream = $disk->readStream($copy);
        $context = hash_init('sha256');

        if (is_resource($stream)) {
            hash_update_stream($context, $stream);
            fclose($stream);
        }

        if (! hash_equals(strtolower($sha256), hash_final($context))) {
            $disk->delete($copy);

            throw new Refusal(409, 'upload_mismatch');
        }

        return $copy;
    }
}
