<?php

declare(strict_types=1);

namespace App\Support\Storage;

use App\Models\UploadLink;
use DateTimeInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * A presigned PUT straight to the private bucket, for one key and content type (docs/trust-and-safety-plan.md,
 * "Uploads"). Neither S3 nor Spaces signs Content-Length, so the link can't bind the size: it's open for a few
 * minutes only (config uploads.link_minutes) and kept in upload_links, where UploadSweeper deletes anything bigger
 * than declared and every version left once it's done; complete checks a copy (ReceivedUploads). Locally, the same
 * flow runs against the signed PUT /dev/upload/{key} route (contract/api.md, "Uploads").
 */
final class Uploads
{
    /**
     * @return array{url: string, method: string, headers: array<string, string>, expiresAt: DateTimeInterface}
     */
    public function presign(string $key, string $contentType, int $bytes): array
    {
        $expiresAt = now()->addMinutes(max(1, (int) config('uploads.link_minutes')));
        $headers = ['Content-Type' => $contentType, 'Content-Length' => (string) $bytes];
        UploadLink::query()->upsert([['upload_key' => $key, 'bytes' => $bytes, 'expires_at' => $expiresAt, 'created_at' => now()]], ['upload_key'], ['bytes', 'expires_at']);

        if (config('filesystems.disks.private.driver') === 's3') {
            /** @var array{url: string, headers: array<string, mixed>} $signed */
            $signed = Storage::disk('private')->temporaryUploadUrl($key, $expiresAt, ['ContentType' => $contentType, 'ContentLength' => $bytes]);

            return ['url' => $signed['url'], 'method' => 'PUT', 'headers' => $headers, 'expiresAt' => $expiresAt];
        }

        $url = URL::temporarySignedRoute('dev.upload', $expiresAt, ['key' => $key, 'type' => $contentType, 'bytes' => $bytes]);

        return ['url' => $url, 'method' => 'PUT', 'headers' => $headers, 'expiresAt' => $expiresAt];
    }
}
