<?php

declare(strict_types=1);

namespace App\Actions\AiVoices;

use App\Enums\AiUploadStatus;
use App\Enums\GuideSource;
use App\Exceptions\Refusal;
use App\Models\AiUpload;
use App\Models\AiVoice;
use App\Models\Template;
use App\Models\TemplateGuide;
use App\Support\AiVoices\AiLicence;
use App\Support\Ids;

/**
 * What a song's current version still needs (worker.ai.needs): its notes to render from, and per part whether a
 * recorded voice sings it, its AI guide, the newest AI upload on its way, and whether a render is wanted (no recorded
 * voice: a new AI one replaces any already there). With every AI voice, withdrawn ones marked inactive.
 */
final class AiNeeds
{
    /**
     * @return array{data: array<string, mixed>, meta: array{voices: list<array{id: string, name: string, active: bool, licensed: bool}>}}
     */
    public function handle(string $slug): array
    {
        $template = Template::query()->official()->with('currentVersion')->where('slug', $slug)->whereNull('deleted_at')->first();
        $version = $template?->currentVersion;

        if ($template === null || $version === null || ! $version->reviewed) {
            throw new Refusal(404, 'not_found');
        }

        $guides = TemplateGuide::withAi()->where('template_version_id', $version->id)->get(['id', 'part_key', 'source', 'ai_voice_id', 'approved_at', 'published_at']);
        $pending = AiUpload::query()->where('template_version_id', $version->id)->whereIn('status', [AiUploadStatus::Uploading->value, AiUploadStatus::Processing->value])
            ->orderByDesc('id')->get()->unique('part_key')->keyBy('part_key');
        $data = (array) $version->data;
        $parts = array_values(array_filter((array) ($data['parts'] ?? []), static fn (mixed $part): bool => is_array($part) && is_string($part['key'] ?? null)));

        return [
            'data' => [
                'template' => (string) $template->slug,
                'version' => $version->version,
                'versionId' => $version->sqid(),
                'title' => (string) $template->title,
                'data' => (object) $data,
                'parts' => array_map(static function (array $part) use ($guides, $pending): array {
                    $mine = $guides->where('part_key', $part['key']);
                    $real = $mine->contains(static fn (TemplateGuide $g): bool => $g->source === GuideSource::Recorded);
                    $ai = $mine->first(static fn (TemplateGuide $g): bool => $g->source === GuideSource::Ai);
                    $upload = $pending->get($part['key']);

                    return [
                        'key' => (string) $part['key'],
                        'name' => (string) ($part['name'] ?? $part['key']),
                        'hasRealGuide' => $real,
                        'aiGuide' => $ai instanceof TemplateGuide && $ai->ai_voice_id !== null ? ['id' => $ai->sqid(), 'voice' => Ids::encode($ai->ai_voice_id), 'publishedAt' => $ai->published_at->utc()->toIso8601ZuluString(), 'review' => (string) $ai->review()] : null,
                        'pending' => $upload instanceof AiUpload ? ['upload' => $upload->sqid(), 'voice' => Ids::encode($upload->ai_voice_id), 'status' => $upload->status->value] : null,
                        'wanted' => ! $real,
                    ];
                }, $parts),
            ],
            'meta' => [
                'voices' => $this->voices(),
            ],
        ];
    }

    /**
     * Every AI voice, for the worker: withdrawn ones inactive, ones waiting for their singer's yes to new terms unlicensed.
     *
     * @return list<array{id: string, name: string, active: bool, licensed: bool}>
     */
    public function voices(): array
    {
        return array_values(AiVoice::query()->with('betaSinger.user')->orderBy('id')->get()->map(static fn (AiVoice $voice): array => ['id' => $voice->sqid(), 'name' => $voice->name, 'active' => $voice->isActive(), 'licensed' => AiLicence::licensed($voice)])->all());
    }
}
