<?php

declare(strict_types=1);

namespace App\Actions\AiVoices;

use App\Http\Resources\Admin\PersonResource;
use App\Http\Resources\GuideRecording;
use App\Models\AiUpload;
use App\Models\AuditLog;
use App\Models\Template;
use App\Models\TemplateGuide;
use App\Models\User;
use App\Support\AiVoices\AiGuides;
use App\Support\Ids;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Hillel's review of new AI guides (admin.aiGuides.*, docs/ai-voices-build-plan.md phase 1c): the queue by song, and
 * approving or rejecting parts singly or a song at a time. Only the current versions' parts are reviewed. The review
 * lives on the AI upload, which outlives a rejected guide's file; approving stamps the guide, which is what lets it
 * reach the app. Rejecting retires the guide and puts the part back on the runner's list with the note.
 */
final class AiReview
{
    public const STATES = ['pending', 'approved', 'rejected'];

    /**
     * @return array{data: list<array<string, mixed>>, meta: array{counts: array{pending: int, approved: int, rejected: int}}}
     */
    public function list(string $state): array
    {
        $uploads = $this->current($state)->with(['templateVersion.template', 'voice', 'guide.aiVoice', 'reviewer'])->orderBy('id')->get();
        $songs = array_values($uploads->groupBy('template_version_id')->map(static function (Collection $parts): array {
            /** @var AiUpload $first */
            $first = $parts->first();
            $version = $first->templateVersion;
            $names = collect((array) ($version->data['parts'] ?? []))->filter(static fn (mixed $p): bool => is_array($p) && is_string($p['key'] ?? null))->mapWithKeys(static fn (array $p): array => [$p['key'] => (string) ($p['name'] ?? $p['key'])]);

            return [
                'templateId' => (string) $version->template->slug,
                'title' => (string) $version->template->title,
                'version' => $version->version,
                'versionId' => $version->sqid(),
                'parts' => array_values($parts->map(static fn (AiUpload $u): array => [
                    'id' => $u->sqid(),
                    'partKey' => $u->part_key,
                    'partName' => (string) ($names[$u->part_key] ?? $u->part_key),
                    'status' => (string) $u->review,
                    'voice' => ['id' => $u->voice->sqid(), 'name' => $u->voice->name],
                    'recording' => $u->guide !== null ? GuideRecording::of($u->guide) : null,
                    'checks' => $u->checks,
                    'renderer' => ['model' => (string) ($u->renderer['model'] ?? ''), 'steps' => (int) ($u->renderer['steps'] ?? 0), 'pitchShift' => (int) ($u->renderer['pitchShift'] ?? 0), 'reference' => (string) ($u->renderer['reference'] ?? '')],
                    'publishedAt' => $u->guide?->published_at->utc()->toIso8601ZuluString(),
                    'note' => $u->review_note,
                    'reviewedAt' => $u->reviewed_at?->utc()->toIso8601ZuluString(),
                    'reviewedBy' => PersonResource::of($u->reviewer),
                ])->all()),
            ];
        })->sortBy('title')->all());

        return ['data' => $songs, 'meta' => ['counts' => [
            'pending' => $this->current('pending')->count(),
            'approved' => $this->current('approved')->count(),
            'rejected' => $this->current('rejected')->count(),
        ]]];
    }

    /**
     * @param  list<string>  $ids  the upload ids the reviewer saw
     */
    public function approve(array $ids, User $admin, ?string $ip = null): int
    {
        return DB::transaction(function () use ($ids, $admin, $ip): int {
            $changed = 0;

            foreach ($this->chosen($ids, ['pending'])->lockForUpdate()->get() as $upload) {
                $upload->forceFill(['review' => 'approved', 'review_note' => null, 'reviewed_at' => now(), 'reviewed_by' => $admin->id])->save();
                TemplateGuide::withAi()->whereKey($upload->template_guide_id)->update(['approved_at' => now()]);
                AuditLog::record('ai.guide_approved', $upload, $admin, ip: $ip);
                $changed++;
            }

            return $changed;
        });
    }

    /**
     * @param  list<string>  $ids  the upload ids the reviewer saw (pending or approved)
     */
    public function reject(array $ids, string $note, User $admin, ?string $ip = null): int
    {
        return DB::transaction(function () use ($ids, $note, $admin, $ip): int {
            $changed = 0;

            foreach ($this->chosen($ids, ['pending', 'approved'])->lockForUpdate()->get() as $upload) {
                $guide = TemplateGuide::withAi()->whereKey($upload->template_guide_id)->lockForUpdate()->first();
                AiGuides::retire($guide !== null ? [$guide] : [], $admin, 'rejected');
                $upload->forceFill(['review' => 'rejected', 'review_note' => $note, 'reviewed_at' => now(), 'reviewed_by' => $admin->id])->save();
                AuditLog::record('ai.guide_rejected', $upload, $admin, after: ['note' => $note], ip: $ip);
                $changed++;
            }

            return $changed;
        });
    }

    /**
     * Uploads in this review state on their song's current version; pending and approved ones only while their guide is
     * live (a real voice may have retired it since).
     *
     * @return Builder<AiUpload>
     */
    private function current(string $state): Builder
    {
        return AiUpload::query()->where('review', $state)
            ->whereIn('template_version_id', Template::query()->official()->whereNotNull('current_version_id')->select('current_version_id'))
            ->when($state !== 'rejected', fn (Builder $query) => $query->whereNotNull('template_guide_id'));
    }

    /**
     * @param  list<string>  $ids
     * @param  list<string>  $states
     * @return Builder<AiUpload>
     */
    private function chosen(array $ids, array $states): Builder
    {
        return AiUpload::query()->whereIn('review', $states)->whereNotNull('template_guide_id')
            ->whereIn('template_version_id', Template::query()->official()->whereNotNull('current_version_id')->select('current_version_id'))
            ->whereKey(array_values(array_filter(array_map(static fn (string $id): ?int => Ids::decode($id), $ids))));
    }
}
