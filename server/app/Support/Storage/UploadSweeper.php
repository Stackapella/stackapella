<?php

declare(strict_types=1);

namespace App\Support\Storage;

use App\Models\UploadLink;
use Aws\S3\S3Client;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * What's left at presigned upload keys (the abuse review, 2026-11-21). A link can't bind its size, so: while a link
 * can still be sent, or just after, anything at its key bigger than declared is deleted at once with every version
 * (the buckets keep versions, so an overwrite or delete alone keeps the bytes); once a link is done (its expiry plus
 * uploads.keep_minutes, time for its complete, which copies the file away), every version left at its key goes and
 * the link with it.
 */
final class UploadSweeper
{
    /**
     * @return array{oversized: int, swept: int}
     */
    public function sweep(): array
    {
        $oversized = 0;
        $swept = 0;
        $disk = Storage::disk('private');

        // Still writable, or written to at its last moment: what's there now is checked (a HEAD each).
        UploadLink::query()->where('expires_at', '>', now()->subMinutes(10))->orderBy('id')->chunkById(500, function ($links) use ($disk, &$oversized): void {
            foreach ($links as $link) {
                if ($disk->exists($link->upload_key) && $disk->size($link->upload_key) > $link->bytes) {
                    $this->purge($link->upload_key);
                    $oversized++;
                }
            }
        });

        UploadLink::query()->where('expires_at', '<', now()->subMinutes((int) config('uploads.keep_minutes')))->orderBy('id')->chunkById(500, function ($links) use (&$swept): void {
            foreach ($links as $link) {
                $this->purge($link->upload_key);
                $link->delete();
                $swept++;
            }
        });

        return ['oversized' => $oversized, 'swept' => $swept];
    }

    /** Every version at this key, and its delete markers, so nothing is kept for the noncurrent window. */
    public function purge(string $key): void
    {
        $disk = Storage::disk('private');
        $adapter = $disk instanceof AwsS3V3Adapter ? $disk : null;

        if ($adapter === null) {
            $disk->delete($key);

            return;
        }

        /** @var S3Client $client */
        $client = $adapter->getClient();
        $bucket = (string) config('filesystems.disks.private.bucket');

        try {
            $versions = $client->listObjectVersions(['Bucket' => $bucket, 'Prefix' => $key]);

            foreach ([...((array) ($versions['Versions'] ?? [])), ...((array) ($versions['DeleteMarkers'] ?? []))] as $version) {
                if (is_array($version) && ($version['Key'] ?? null) === $key) {
                    $client->deleteObject(['Bucket' => $bucket, 'Key' => $key, 'VersionId' => $version['VersionId']]);
                }
            }
        } catch (Throwable $e) {
            // The key can't list or delete versions (a scoped key): the current object at least, and ops told once a day.
            $disk->delete($key);

            if (Cache::add('uploads:versions-refused', true, 86400)) {
                report($e);
            }
        }
    }
}
