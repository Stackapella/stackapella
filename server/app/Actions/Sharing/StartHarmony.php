<?php

declare(strict_types=1);

namespace App\Actions\Sharing;

use App\Actions\Counts\RecordCounts;
use App\Actions\Gallery\OpenParts;
use App\Actions\OwnChoir\ChoirTogether;
use App\Actions\Projects\Owned;
use App\Enums\OwnChoirStatus;
use App\Exceptions\Refusal;
use App\Models\Harmony;
use App\Models\OwnChoirPart;
use App\Models\OwnChoirRequest;
use App\Models\Part;
use App\Models\Project;
use App\Models\Take;
use App\Models\TemplateGuide;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Support\AiVoices\AiGuides;
use App\Support\Gallery\Gallery;
use App\Support\Ids;
use App\Support\Moderation\TextFilter;
use App\Support\OwnChoir\OwnChoir;
use App\Support\Storage\Uploads;
use App\Support\Templates\Imported;
use App\Support\Trust\TrustLevel;
use App\Support\Usage\InFlight;
use App\Support\Usage\Plans;
use App\Support\Usage\UploadBytes;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * harmonies.create: a mix rendered on the device, checked against its project (the mix check,
 * docs/trust-and-safety-plan.md), then a presigned PUT for the WAV. The same changeId again
 * answers the same harmony (an outbox retry).
 */
final class StartHarmony
{
    public function __construct(private readonly TrustLevel $trust, private readonly Uploads $uploads, private readonly ShareGate $gate, private readonly Plans $plans, private readonly UploadBytes $uploadBytes) {}

    /**
     * @param  array{changeId: string, projectId: string, partId?: string|null, title?: string|null, visibility: string, sampleRate: int, channels: int, frames: int, bytes: int, sha256: string, beatZeroSeconds?: float|int|null, guides?: list<string>|null, choirParts?: list<string>|null, parentId?: string|null, fillPart?: string|null, tracks: list<array{name: string, color: string, peaks: string, kind?: string}>, replaces?: string|null}  $data
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

        $upload = $harmony->status === Harmony::UPLOADING ? $this->uploads->presign($harmony->upload_key, 'audio/wav', $harmony->upload_bytes) : null;

        // "Update that link": the answer is the share being replaced (its address), with the new mix's upload.
        if ($harmony->replaces_id !== null) {
            return ['harmony' => Harmony::query()->findOrFail($harmony->replaces_id), 'upload' => $upload];
        }

        return ['harmony' => $harmony, 'upload' => $upload];
    }

    /**
     * @param  array{changeId: string, projectId: string, partId?: string|null, title?: string|null, visibility: string, sampleRate: int, channels: int, frames: int, bytes: int, sha256: string, beatZeroSeconds?: float|int|null, guides?: list<string>|null, choirParts?: list<string>|null, parentId?: string|null, fillPart?: string|null, tracks: list<array{name: string, color: string, peaks: string, kind?: string}>, replaces?: string|null}  $data
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

        // "Update that link" (Hillel, 2026-11-23): their own live share of this project, given a new mix at its address.
        $replaces = null;

        if (($data['replaces'] ?? null) !== null) {
            $replaces = Harmony::query()->where('public_id', (string) $data['replaces'])->where('user_id', $user->id)->where('project_id', $project->id)
                ->where('status', Harmony::READY)->whereNull('deleted_at')->whereNull('removed_at')->lockForUpdate()->first() ?? throw new Refusal(404, 'not_found');

            // One new mix at a time: an earlier one not yet uploaded makes way; one being processed finishes first.
            $pending = Harmony::query()->where('replaces_id', $replaces->id)->whereIn('status', [Harmony::UPLOADING, Harmony::PROCESSING])->get();

            if ($pending->contains('status', Harmony::PROCESSING)) {
                throw new Refusal(409, 'share_updating');
            }

            foreach ($pending as $old) {
                Storage::disk('private')->delete((string) $old->upload_key);
                $old->delete();
            }
        }

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

        // Every presigned upload counts toward the day's bytes (the abuse review).
        $this->uploadBytes->count($user, $data['bytes']);
        InFlight::ensure($user, Harmony::class);

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
            'replaces_id' => $replaces?->id,
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
            'own_voice_friends' => $choir['friends'] === [] ? null : $choir['friends'],
            // Their own real voice in the mix (2026-11-23), kept so the share comes down when it's deleted.
            'real_voice' => $choir['parts'] === [] ? null : $this->realVoice($user, (array) ($data['realVoice'] ?? []), is_string($choirSaved['requestId'] ?? null) ? $choirSaved['requestId'] : null),
            'template_version_id' => $version?->id,
            'words_part_key' => $version !== null ? ($part->template_part_key ?? $this->melodyKey($version)) : null,
            'bpm' => $project->bpm,
            'beat_zero_ms' => isset($data['beatZeroSeconds']) ? (int) round((float) $data['beatZeroSeconds'] * 1000) : null,
            'title_held_at' => TextFilter::holds($title) ? now() : null,
        ])->save();

        // The choir's anonymous counts (2026-11-23): a share holding someone's AI choir.
        if ($choir['parts'] !== []) {
            app(RecordCounts::class)->serverAmount('choir_share', 1);
        }

        return $harmony;
    }

    /**
     * Which of their own recordings the mix holds, as the server knows them: the project's choir song's lead (`take`),
     * their saved voice's clip (`clip`); null for none. Only ever beside their AI parts: a recording alone is never
     * shared (the coordinator, 2026-11-23).
     *
     * @param  array<mixed>  $claimed
     * @return array{take?: int, clip?: int}|null
     */
    private function realVoice(User $user, array $claimed, ?string $songSqid): ?array
    {
        $real = [];

        if (in_array('take', $claimed, true) && $songSqid !== null) {
            $song = OwnChoirRequest::query()->whereKey(Ids::decode($songSqid) ?? 0)->where('user_id', $user->id)->whereNotNull('lead_key')->first();
            $real += $song === null ? [] : ['take' => $song->id];
        }

        if (in_array('clip', $claimed, true) && ($voice = OwnChoir::voice($user)) !== null && $voice->clip_key !== null) {
            $real['clip'] = $voice->id;
        }

        return $real === [] ? null : $real;
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
     * @return array{parts: list<string>, friends: list<array{part: string, name: string|null, member?: int, song?: int}>, seconds: float}
     */
    private function ownVoice(array $sqids, User $user, array $requestSqids = []): array
    {
        $ids = array_values(array_filter(array_map(static fn (string $sqid): ?int => Ids::decode($sqid), $sqids)));
        $requests = array_values(array_filter(array_map(static fn (string $sqid): ?int => Ids::decode($sqid), $requestSqids)));

        if ($ids === [] && $requests === []) {
            return ['parts' => [], 'friends' => [], 'seconds' => 0.0];
        }

        // The choir songs in the mix: their own, or one they sing in as a friend (choir together); never anyone else's.
        $picked = OwnChoirPart::query()->with('request')->whereKey($ids)->get();
        $songIds = array_values(array_unique([...$requests, ...$picked->map(static fn (OwnChoirPart $p): int => (int) ($p->request->parent_request_id ?? $p->own_choir_request_id))->all()]));
        $songs = OwnChoirRequest::query()->with(['user', 'templateVersion'])->whereKey($songIds)->where('status', OwnChoirStatus::Ready->value)->whereNull('parent_request_id')->get()
            ->filter(static fn (OwnChoirRequest $song): bool => $song->user_id === $user->id || ChoirTogether::activeMembers($song)->where('user_id', $user->id)->exists());
        $names = [];
        $friends = [];
        $seconds = 0.0;

        foreach ($songs as $song) {
            $byKey = collect((array) ($song->templateVersion->data['parts'] ?? []))->filter(static fn (mixed $p): bool => is_array($p))->keyBy('key');
            // Every part as it plays (a whole song, settings.choir.requestId), or those the app named (choirParts).
            $active = (new Collection(ChoirTogether::activeParts($song)->all()))->load('request.member.user')
                ->filter(static fn (OwnChoirPart $p): bool => in_array((int) ($p->request->parent_request_id ?? $p->own_choir_request_id), $requests, true) || in_array($p->id, $ids, true));

            foreach ($active as $part) {
                $partName = is_string($byKey[$part->part_key]['name'] ?? null) ? $byKey[$part->part_key]['name'] : $part->part_key;
                $member = $part->request->parent_request_id !== null ? $part->request->member : null;
                $seconds = max($seconds, $part->frames / max(1, $part->sample_rate));

                if (($member === null && $song->user_id === $user->id) || ($member !== null && $member->user_id === $user->id)) {
                    // In the sharer's own voice.
                    $names[] = $partName;
                } elseif ($member !== null) {
                    // A friend's: named only if they chose to show it, and only a name the page may show.
                    $friends[] = ['part' => $partName, 'name' => $member->show_name ? self::shownName($member->user) : null, 'member' => $member->id];
                } else {
                    // The owner's, in a friend's share of the song they sing in: owners are named.
                    $friends[] = ['part' => $partName, 'name' => self::shownName($song->user), 'song' => $song->id];
                }
            }
        }

        return ['parts' => array_values(array_unique($names)), 'friends' => array_values(collect($friends)->unique('part')->all()), 'seconds' => $seconds];
    }

    /** A first name as a shared page may show it: none for a hidden profile or a name the filters hold. */
    private static function shownName(?User $person): ?string
    {
        if ($person === null || Gallery::hidden($person) || TextFilter::hasLink($person->name) || TextFilter::holds($person->name)) {
            return null;
        }

        $first = trim(explode(' ', trim($person->name))[0] ?? '');

        return $first === '' ? null : $first;
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
