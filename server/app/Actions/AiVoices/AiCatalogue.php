<?php

declare(strict_types=1);

namespace App\Actions\AiVoices;

use App\Enums\AiUploadStatus;
use App\Enums\GuideSource;
use App\Models\AiUpload;
use App\Models\Template;
use App\Models\TemplateGuide;
use App\Support\Ids;

/**
 * worker.ai.catalogue: every official, reviewed song's current version and what each part still needs, for the voices
 * lane's catalogue runner (docs/ai-voices-build-plan.md, phase 1c). Read in a handful of queries for the whole
 * catalogue. `todo` is exactly what to render: no recorded voice, no AI guide pending or approved, nothing in flight.
 */
final class AiCatalogue
{
    public function __construct(private readonly AiNeeds $needs) {}

    /**
     * @return array{data: list<array<string, mixed>>, meta: array{voices: list<array{id: string, name: string, active: bool, licensed: bool}>}}
     */
    public function handle(): array
    {
        $songs = Template::query()->official()->with('currentVersion')->whereNull('deleted_at')->whereNotNull('current_version_id')->orderBy('title')->get()
            ->filter(static fn (Template $t): bool => (bool) $t->currentVersion?->reviewed)->values();
        $versionIds = $songs->pluck('current_version_id')->all();

        $guides = TemplateGuide::withAi()->whereIn('template_version_id', $versionIds)->get(['id', 'template_version_id', 'part_key', 'source', 'ai_voice_id', 'approved_at'])
            ->groupBy(static fn (TemplateGuide $g): string => $g->template_version_id.':'.$g->part_key);
        $uploads = AiUpload::query()->whereIn('template_version_id', $versionIds)->orderByDesc('id')->get(['id', 'template_version_id', 'part_key', 'ai_voice_id', 'status', 'review', 'review_note', 'reviewed_at']);
        $slot = static fn (AiUpload $u): string => $u->template_version_id.':'.$u->part_key;
        // The newest upload per part (in flight or not), and its newest rejection: a later render that failed (a
        // withdrawn voice, new terms, never sent) doesn't hide Hillel's note.
        $latest = $uploads->unique($slot)->keyBy($slot);
        $rejections = $uploads->where('review', 'rejected')->unique($slot)->keyBy($slot);

        $data = [];

        foreach ($songs as $song) {
            $version = $song->currentVersion;

            if ($version === null) {
                continue;
            }

            $template = (array) $version->data;
            $beat = 60 / max(1.0, (float) ($template['tempo'] ?? 0));
            $parts = [];

            foreach (array_values(array_filter((array) ($template['parts'] ?? []), static fn (mixed $p): bool => is_array($p) && is_string($p['key'] ?? null))) as $part) {
                $slot = $version->id.':'.$part['key'];
                $mine = $guides->get($slot, collect());
                $real = $mine->contains(static fn (TemplateGuide $g): bool => $g->source === GuideSource::Recorded);
                $ai = $mine->first(static fn (TemplateGuide $g): bool => $g->source === GuideSource::Ai);
                $upload = $latest->get($slot);
                $inFlight = $upload instanceof AiUpload && in_array($upload->status, [AiUploadStatus::Uploading, AiUploadStatus::Processing], true);
                $rejection = $rejections->get($slot);
                $rejected = $rejection instanceof AiUpload && $ai === null && ! $real;
                $notes = array_values(array_filter((array) ($part['guide']['notes'] ?? []), 'is_array'));
                $end = $notes === [] ? 0.0 : max(array_map(static fn (array $n): float => (float) ($n['beat'] ?? 0) + (float) ($n['length'] ?? 0), $notes));

                $parts[] = [
                    'key' => (string) $part['key'],
                    'name' => (string) ($part['name'] ?? $part['key']),
                    'range' => ['low' => (int) ($part['range']['low'] ?? 0), 'high' => (int) ($part['range']['high'] ?? 0)],
                    'seconds' => round($end * $beat, 2),
                    'hasRealGuide' => $real,
                    'aiGuide' => $ai instanceof TemplateGuide && $ai->ai_voice_id !== null ? ['id' => $ai->sqid(), 'voice' => Ids::encode($ai->ai_voice_id), 'review' => (string) $ai->review()] : null,
                    'pending' => $inFlight ? ['upload' => $upload->sqid(), 'voice' => Ids::encode($upload->ai_voice_id), 'status' => $upload->status->value] : null,
                    'rejected' => $rejected ? ['upload' => $rejection->sqid(), 'voice' => Ids::encode($rejection->ai_voice_id), 'note' => (string) $rejection->review_note, 'at' => $rejection->reviewed_at?->utc()->toIso8601ZuluString() ?? ''] : null,
                    'todo' => ! $real && $ai === null && ! $inFlight,
                ];
            }

            $data[] = [
                'template' => (string) $song->slug,
                'version' => $version->version,
                'versionId' => $version->sqid(),
                'title' => (string) $song->title,
                'voicings' => (object) array_map(static fn (mixed $keys): array => array_values(array_filter((array) $keys, 'is_string')), (array) ($template['voicings'] ?? [])),
                'parts' => $parts,
            ];
        }

        return ['data' => $data, 'meta' => ['voices' => $this->needs->voices()]];
    }
}
