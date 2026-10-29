<?php

declare(strict_types=1);

namespace App\Support\Storage;

use DateTimeInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * A presigned PUT straight to the private bucket, signed for one key, content type and exact size,
 * so it can't carry a bigger or different file (docs/trust-and-safety-plan.md, "Uploads"). Locally,
 * the same flow runs against the signed PUT /dev/upload/{key} route, so the app does the same thing
 * everywhere (contract/api.md, "Uploads").
 */
final class Uploads
{
    /**
     * @return array{url: string, method: string, headers: array<string, string>, expiresAt: DateTimeInterface}
     */
    public function presign(string $key, string $contentType, int $bytes, int $minutes = 30): array
    {
        $expiresAt = now()->addMinutes($minutes);
        $headers = ['Content-Type' => $contentType, 'Content-Length' => (string) $bytes];
        $disk = Storage::disk('private');

        if (config('filesystems.disks.private.driver') === 's3') {
            /** @var array{url: string, headers: array<string, mixed>} $signed */
            $signed = $disk->temporaryUploadUrl($key, $expiresAt, ['ContentType' => $contentType, 'ContentLength' => $bytes]);

            return ['url' => $signed['url'], 'method' => 'PUT', 'headers' => $headers, 'expiresAt' => $expiresAt];
        }

        $url = URL::temporarySignedRoute('dev.upload', $expiresAt, ['key' => $key, 'type' => $contentType, 'bytes' => $bytes]);

        return ['url' => $url, 'method' => 'PUT', 'headers' => $headers, 'expiresAt' => $expiresAt];
    }
}
