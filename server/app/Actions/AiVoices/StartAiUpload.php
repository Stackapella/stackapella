<?php

declare(strict_types=1);

namespace App\Actions\AiVoices;

use App\Enums\AiUploadStatus;
use App\Exceptions\Refusal;
use App\Models\AiUpload;
use App\Models\AiVoice;
use App\Models\TemplateGuide;
use App\Models\TemplateVersion;
use App\Support\Ids;
use App\Support\Storage\Uploads;
use Illuminate\Support\Str;

/**
 * worker.ai.uploads.create: the server records the checked master's numbers, the voices lane's gates and renderer, and
 * gives back a presigned PUT (contract/api.md, "Uploads"). Only for an official, reviewed song's current version, a part
 * it has, an active voice, and a part no recorded voice sings.
 */
final class StartAiUpload
{
    public function __construct(private readonly Uploads $uploads) {}

    /**
     * @param  array{versionId: string, part: string, voice: string, sampleRate: int, frames: int, bytes: int, sha256: string, checks: array<string, mixed>, renderer: array<string, mixed>}  $master
     * @return array{upload: AiUpload, put: array{url: string, method: string, headers: array<string, string>, expiresAt: \DateTimeInterface}}
     */
    public function handle(array $master): array
    {
        $version = TemplateVersion::query()->with('template')->find(Ids::decode($master['versionId']) ?? 0);
        $voice = AiVoice::query()->find(Ids::decode($master['voice']) ?? 0);
        $template = $version?->template;
        $parts = array_column(array_filter((array) ($version->data['parts'] ?? []), 'is_array'), 'key');

        if ($version === null || $template === null || $voice === null || ! $template->isOfficial() || $template->deleted_at !== null || ! in_array($master['part'], $parts, true)) {
            throw new Refusal(404, 'not_found');
        }

        if (! $voice->isActive()) {
            throw new Refusal(403, 'voice_withdrawn');
        }

        if ($template->current_version_id !== $version->id || ! $version->reviewed) {
            throw new Refusal(409, 'template_changed');
        }

        if (TemplateGuide::query()->where('template_version_id', $version->id)->where('part_key', $master['part'])->exists()) {
            throw new Refusal(409, 'real_voice_exists');
        }

        $upload = new AiUpload(['part_key' => $master['part']]);
        $upload->forceFill([
            'template_version_id' => $version->id,
            'ai_voice_id' => $voice->id,
            'status' => AiUploadStatus::Uploading,
            // Under uploads/, which the private bucket's lifecycle expires after a day; random, never guessable.
            'upload_key' => 'uploads/ai/'.Str::lower(Str::random(32)).'.wav',
            'sample_rate' => $master['sampleRate'],
            'frames' => $master['frames'],
            'bytes' => $master['bytes'],
            'sha256' => strtolower($master['sha256']),
            'checks' => $master['checks'],
            'renderer' => $master['renderer'],
        ])->save();

        return ['upload' => $upload, 'put' => $this->uploads->presign($upload->upload_key, 'audio/wav', $upload->bytes)];
    }
}
