<?php

declare(strict_types=1);

namespace App\Actions\Sharing;

use App\Actions\Gallery\OpenParts;
use App\Actions\Projects\Owned;
use App\Enums\OwnChoirStatus;
use App\Exceptions\Refusal;
use App\Models\Harmony;
use App\Models\OwnChoirPart;
use App\Models\Part;
use App\Models\Project;
use App\Models\Take;
use App\Models\TemplateGuide;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Support\AiVoices\AiGuides;
use App\Support\Ids;
use App\Support\Moderation\TextFilter;
use App\Support\Storage\Uploads;
use App\Support\Templates\Imported;
use App\Support\Trust\TrustLevel;
use App\Support\Usage\Plans;
use DateTimeInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * harmonies.create: a mix rendered on the device, checked against its project (the mix check,
 * docs/trust-and-safety-plan.md), then a presigned PUT for the WAV. The same changeId again
 * answers the same harmony (an outbox retry).
 */
final class StartHarmony
{
    public function __construct(private readonly TrustLevel $trust, private readonly Uploads $uploads, private readonly ShareGate $gate, private readonly Plans $plans) {}

    /**
     * @param  array{changeId: string, projectId: string, partId?: string|null, title?: string|null, visibility: string, sampleRate: int, channels: int, frames: int, bytes: int, sha256: string, beatZeroSeconds?: float|int|null, guides?: list<string>|null, choirParts?: list<string>|null, parentId?: string|null, fillPart?: string|null, tracks: list<array{name: string, color: string, peaks: string, kind?: string}>}  $data
     * @return array{harmony: Harmony, upload: array{url: string, method: string, headers: array<string, string>, expiresAt: DateTimeInterface}|null}
     */
    public function handle(User $user, array $data, string $ip): array
    {
        $data['sha256'] = strtolower($data['sha256']);

        try {
            $harmony = DB::transaction(fn (): Harmony => $this->start($user, $data, $ip));
        } catch (UniqueConstraintViolationException) {
            // The same share sent twice at once: the other request made it.
            $harmony = DB::transaction(fn (): Harmony => $this->start($user, $data, $ip));
        }

        return ['harmony' => $harmony, 'upload' => $harmony->status === Harmony::UPLOADING ? $this->uploads->presign($harmony->upload_key, 'audio/wav', $harmony->upload_bytes, minutes: 10) : null];
    }

    /**
     * @param  array{changeId: string, projectId: string, partId?: string|null, title?: string|null, visibility: string, sampleRate: int, channels: int, frames: int, bytes: int, sha256: string, beatZeroSeconds?: float|int|null, guides?: list<string>|null, choirParts?: list<string>|null, parentId?: string|null, fillPart?: string|null, tracks: list<array{name: string, color: string, peaks: string, kind?: string}>}  $data
     */
    private function start(User $user, array $data, string $ip): Harmony
    {
        Owned::lockAccount($user);

        $existing = Harmony::query()->where('user_id', $user->id)->where('change_id', $data['changeId'])->first();

        if ($existing !== null) {
            $same = $existing->sha256 === $data['sha256'] && $existing->frames === $data['frames'] && $existing->sample_rate === $data['sampleRate'] && $existing->channels === $data['channels'];

            if (! $same) {
                throw ValidationException::withMessages(['changeId' => __('validation.harmony_id')]);
            }

            return $existing;
        }

        if ($this->trust->of($user) === TrustLevel::NEW) {
            throw new Refusal(403, 'email_unverified');
        }

        $project = Project::query()->where('uuid', $data['projectId'])->where('user_id', $user->id)->first();

        if ($project === null) {
            throw new Refusal(409, 'project_missing');
        }

        Owned::live($project);

        $part = null;
        if (($data['partId'] ?? null) !== null) {
            $part = Part::query()->where('uuid', $data['partId'])->where('project_id', $project->id)->first() ?? throw new Refusal(409, 'part_missing');
        }

        // A fill (N5): sung for someone's open part, its mix as long as their stems.
        $fill = ($data['parentId'] ?? null) !== null ? app(OpenParts::class)->checkFill($user, $project, (string) $data['parentId'], (string) ($data['fillPart'] ?? '')) : null;
        // Hear yourself as a choir: the sharer's own AI parts count as something to share (a fresh choir song has no take).
        // Those the client names, and those the project records itself (settings.choir), so a share is labelled whatever
        // the client sends (the security review).
        $settings = (array) $project->settings;
        $choirSaved = is_array($settings['choir'] ?? null) ? $settings['choir'] : [];
        $choir = $this->ownVoice(
            [...($data['choirParts'] ?? []), ...array_values(array_filter(array_map(static fn (mixed $p): mixed => is_array($p) ? ($p['id'] ?? null) : null, (array) ($choirSaved['parts'] ?? [])), 'is_string'))],
            $user,
            is_string($choirSaved['requestId'] ?? null) ? [$choirSaved['requestId']] : [],
        );
        ['singers' => $singers, 'takes' => $takes] = $this->checkMix($project, $part, $data['frames'] / $data['sampleRate'], $fill['seconds'] ?? 0.0, $choir['seconds']);

        if ($this->plans->storageUsed($user) + $data['bytes'] > $this->plans->storageLimit($user)) {
            throw $this->plans->storageFull($user);
        }

        if ($data['visibility'] === Harmony::UNLISTED) {
            $this->gate->mayShare($user, $ip);
        }

        $version = $project->template_slug !== null
            ? TemplateVersion::query()->whereHas('template', fn ($query) => $query->where('slug', $project->template_slug))->where('version', $project->template_version)->first()
            : null;

        // Sung from imported sheet music: kept to the singer, never a link anyone can open.
        if ($data['visibility'] === Harmony::UNLISTED && (Imported::project($project) || $version?->template?->isImported() === true)) {
            Imported::refuse();
        }

        $title = trim((string) ($data['title'] ?? ''));

        $harmony = new Harmony([
            'title' => $title,
            'sample_rate' => $data['sampleRate'],
            'channels' => $data['channels'],
            'frames' => $data['frames'],
            'upload_bytes' => $data['bytes'],
            'sha256' => $data['sha256'],
        ]);
        $harmony->forceFill([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'part_id' => $part?->id,
            'singer_ids' => $singers,
            'take_ids' => $takes,
            'parent_id' => $fill !== null ? $fill['parent']->id : null,
            'fill_part_key' => $fill !== null ? (string) ($data['fillPart'] ?? '') : null,
            'part_name' => $part !== null ? $this->partName($part, $version) : null,
            'change_id' => $data['changeId'],
            'visibility' => $data['visibility'],
            'status' => Harmony::UPLOADING,
            // Random and immutable, never guessable from the person or the song.
            'upload_key' => 'uploads/harmonies/'.Str::lower(Str::random(32)).'.wav',
            // Each track's kind (a voice unless the app says it's an instrument) words the credits.
            'tracks' => array_map(static fn (array $track): array => ['name' => $track['name'], 'color' => strtolower($track['color']), 'peaks' => $track['peaks'], 'kind' => ($track['kind'] ?? 'voice') === 'instrument' ? 'instrument' : 'voice'], $data['tracks']),
            'guide_ids' => $guideIds = $this->guideIds($data['guides'] ?? [], $version, $user),
            // Its AI parts kept on the share itself: a real take or a withdrawal can retire the guide later, while the
            // mix still holds the AI singing, and its page must still say so.
            'ai_parts' => $this->aiParts([...$guideIds, ...$this->untakenAiGuides($project, $version, $user)]),
            'own_voice_parts' => $choir['parts'] === [] ? null : $choir['parts'],
            'template_version_id' => $version?->id,
            'words_part_key' => $version !== null ? ($part->template_part_key ?? $this->melodyKey($version)) : null,
            'bpm' => $project->bpm,
            'beat_zero_ms' => isset($data['beatZeroSeconds']) ? (int) round((float) $data['beatZeroSeconds'] * 1000) : null,
            'title_held_at' => TextFilter::holds($title) ? now() : null,
        ])->save();

        return $harmony;
    }

    /**
     * The project (or the part) has a recording, and the mix is no longer than it plus the
     * reverb's tail. Answers who else sang the takes in it (the project's singers), and which
     * takes they are (only these may become guide voices).
     *
     * @return array{singers: list<int>, takes: list<int>}
     */
    private function checkMix(Project $project, ?Part $part, float $seconds, float $stems = 0.0, float $choir = 0.0): array
    {
        $parts = Part::query()->with('currentTake')->where('project_id', $project->id)->when($part !== null, fn ($query) => $query->whereKey($part?->id))->get();
        $longest = null;
        $singers = [];
        $takes = [];

        foreach ($parts as $candidate) {
            $take = $candidate->currentTake;

            if ($take === null || $take->status !== Take::READY || $take->binned_at !== null) {
                continue;
            }

            if ($take->recorded_by !== null && $take->recorded_by !== $project->user_id) {
                $singers[$take->recorded_by] = $take->recorded_by;
            }

            $takes[] = $take->id;

            $end = ($take->frames + max(0, $take->offset_frames + $candidate->nudge_frames)) / $take->sample_rate;
            $longest = max($longest ?? 0, $end);
        }

        if ($longest === null && $choir <= 0.0) {
            throw new Refusal(409, 'no_takes');
        }

        if ($seconds > max($longest ?? 0.0, $stems, $choir) + (float) config('sharing.tail_seconds')) {
            throw new Refusal(409, 'mix_mismatch');
        }

        // Anyone who ever sang in it counts as a singer to ask, not only those on the current takes:
        // the mix is made on the device and could hold a replaced take's voice (security review,
        // 2026-11-01).
        $ever = Take::query()->where('project_id', $project->id)->when($part !== null, fn ($query) => $query->where('part_id', $part?->id))
            ->whereNotNull('recorded_by')->where('recorded_by', '!=', $project->user_id)->distinct()->pluck('recorded_by');

        foreach ($ever as $id) {
            $singers[(int) $id] = (int) $id;
        }

        ksort($singers);
        sort($takes);

        return ['singers' => array_values($singers), 'takes' => $takes];
    }

    /**
     * The sharer's own AI parts in the mix (Hear yourself as a choir): only parts of their own ready songs, by name, kept
     * on the share so its page labels them as AI even after the song is deleted; and how long the longest is (the mix
     * may be that long).
     *
     * @param  list<string>  $sqids
     * @param  list<string>  $requestSqids  songs whose every ready part counts (the project's settings.choir.requestId)
     * @return array{parts: list<string>, seconds: float}
     */
    private function ownVoice(array $sqids, User $user, array $requestSqids = []): array
    {
        $ids = array_values(array_filter(array_map(static fn (string $sqid): ?int => Ids::decode($sqid), $sqids)));
        $requests = array_values(array_filter(array_map(static fn (string $sqid): ?int => Ids::decode($sqid), $requestSqids)));

        if ($ids === [] && $requests === []) {
            return ['parts' => [], 'seconds' => 0.0];
        }

        $parts = OwnChoirPart::query()->with('request.templateVersion')->where(fn ($query) => $query->whereKey($ids)->orWhereIn('own_choir_request_id', $requests))->where('status', 'ready')
            ->whereHas('request', fn ($query) => $query->where('user_id', $user->id)->where('status', OwnChoirStatus::Ready->value))->orderBy('id')->get();
        $names = array_values($parts->map(static function (OwnChoirPart $part): string {
            $named = collect((array) ($part->request->templateVersion->data['parts'] ?? []))->first(static fn (mixed $p): bool => is_array($p) && ($p['key'] ?? null) === $part->part_key);

            return is_array($named) && is_string($named['name'] ?? null) ? $named['name'] : $part->part_key;
        })->unique()->all());

        return ['parts' => $names, 'seconds' => (float) $parts->max(static fn (OwnChoirPart $p): float => $p->frames / max(1, $p->sample_rate))];
    }

    /**
     * The song's AI guides this person heard for parts with no take of theirs in the mix: the app plays a part's default
     * voice without saving it, so these are labelled from the song itself.
     *
     * @return list<int>
     */
    private function untakenAiGuides(Project $project, ?TemplateVersion $version, User $user): array
    {
        if ($version === null || ! AiGuides::on($user)) {
            return [];
        }

        $taken = Part::query()->where('project_id', $project->id)->whereHas('currentTake', fn ($query) => $query->where('status', Take::READY)->whereNull('binned_at'))
            ->whereNotNull('template_part_key')->pluck('template_part_key')->all();

        return array_values(array_map('intval', AiGuides::shownWith(true)->where('source', 'ai')->where('template_version_id', $version->id)->whereNotIn('part_key', $taken)->pluck('id')->all()));
    }

    /**
     * @param  list<int>  $guideIds
     * @return list<array{part: string, voice: int|null}>|null
     */
    private function aiParts(array $guideIds): ?array
    {
        $parts = array_values(TemplateGuide::withAi()->whereKey(array_values(array_unique($guideIds)))->where('source', 'ai')->orderBy('id')->get(['part_key', 'ai_voice_id'])
            ->map(static fn (TemplateGuide $g): array => ['part' => $g->part_key, 'voice' => $g->ai_voice_id])->all());

        return $parts === [] ? null : $parts;
    }

    /**
     * @param  list<string>  $sqids
     * @return list<int>
     */
    private function guideIds(array $sqids, ?TemplateVersion $version, User $user): array
    {
        $ids = array_values(array_filter(array_map(static fn (string $sqid): ?int => Ids::decode($sqid), $sqids)));

        if ($version === null || $ids === []) {
            return [];
        }

        // AI guides too, those this person could hear (docs/ai-voices-build-plan.md): its credits name them as AI.
        return array_values(array_map('intval', AiGuides::shownWith(AiGuides::on($user))->whereKey($ids)->where('template_version_id', $version->id)->orderBy('id')->pluck('id')->all()));
    }

    /**
     * The part's name as the page, card and video show it: the person's own, unless it carries a
     * link or something on the word lists (it was never checked when the part was named), then
     * the template's name for it.
     */
    private function partName(Part $part, ?TemplateVersion $version): string
    {
        if (! TextFilter::hasLink($part->name) && ! TextFilter::holds($part->name)) {
            return $part->name;
        }

        foreach ((array) ($version->data['parts'] ?? []) as $candidate) {
            if (is_array($candidate) && ($candidate['key'] ?? null) === $part->template_part_key && is_string($candidate['name'] ?? null)) {
                return $candidate['name'];
            }
        }

        return __('sharing.one_part');
    }

    /**
     * Whose words the video sings: the melody's part, or the first part.
     */
    private function melodyKey(TemplateVersion $version): ?string
    {
        $parts = array_values(array_filter((array) ($version->data['parts'] ?? []), 'is_array'));

        foreach ($parts as $part) {
            if (($part['melody'] ?? false) === true) {
                return (string) $part['key'];
            }
        }

        return isset($parts[0]['key']) ? (string) $parts[0]['key'] : null;
    }
}
