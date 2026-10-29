<?php

declare(strict_types=1);

namespace App\Actions\Choirs;

use App\Actions\Notices\Notices;
use App\Exceptions\Refusal;
use App\Jobs\ProcessChoirVoice;
use App\Models\AuditLog;
use App\Models\Choir;
use App\Models\ChoirVoice;
use App\Models\Notice;
use App\Models\Report;
use App\Models\Score;
use App\Models\Take;
use App\Models\Template;
use App\Models\User;
use App\Support\Flags;
use App\Support\Gallery\Gallery;
use App\Support\Gallery\GalleryViews;
use App\Support\Trust\TrustLevel;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The virtual choir (N6; docs/more-features-plan.md, 6; contract/api.md, "The virtual choir
 * (N6)"): each of our songs has one, behind the `virtual_choir` flag. Anyone 18 or over with a
 * verified email adds their part (a take of theirs at the song's own key and tempo, scoring at
 * least MIN_SCORE); BuildChoirMix mixes them hourly when they've changed.
 */
final class Choirs
{
    /** The lowest pitch score a voice may join with. */
    public const MIN_SCORE = 60;

    /** The newest this many voices of each part are in the mix. */
    public const MAX_PER_PART = 60;

    public function __construct(private readonly Flags $flags, private readonly TrustLevel $trust, private readonly Notices $notices) {}

    public function isOpen(): bool
    {
        return $this->flags->on('virtual_choir');
    }

    /**
     * One of our songs' choir. Reading never makes one (no data-changing GETs): a song nobody has
     * joined yet answers as an empty choir; joining makes it.
     */
    public function choirFor(string $song, bool $make = false): Choir
    {
        $template = Template::query()->with('currentVersion')->where('slug', $song)->whereNull('owner_id')->whereNull('deleted_at')->whereNotNull('current_version_id')->first();

        if ($template === null || ! $this->isOpen()) {
            throw new Refusal(404, 'not_found');
        }

        $choir = $make ? Choir::query()->firstOrCreate(['template_id' => $template->id]) : (Choir::query()->where('template_id', $template->id)->first() ?? new Choir(['template_id' => $template->id]));
        $choir->setRelation('template', $template);

        return $choir;
    }

    /**
     * Voices the choir shows and mixes: ready, not taken out, not held by reports.
     *
     * @return Builder<ChoirVoice>
     */
    public static function heard(Choir $choir): Builder
    {
        return ChoirVoice::query()->where('choir_id', $choir->id ?? 0)->where('status', ChoirVoice::READY)->whereNull('hidden_at')->whereNull('held_at');
    }

    /**
     * choirs.show.
     *
     * @return array<string, mixed>
     */
    public function show(string $song, ?User $viewer): array
    {
        $choir = $this->choirFor($song);
        $data = $choir->template->currentVersion->data ?? [];
        $counts = self::heard($choir)->selectRaw('part_key, count(*) as n')->groupBy('part_key')->pluck('n', 'part_key');
        $mine = $viewer !== null && $choir->exists ? ChoirVoice::query()->with('user')->where('choir_id', $choir->id)->where('user_id', $viewer->id)->first() : null;

        return [
            'song' => ['id' => $choir->template->slug, 'title' => $choir->template->title, 'version' => (int) $choir->template->currentVersion?->version],
            'bpm' => (float) ($data['tempo'] ?? 0),
            'key' => (string) ($data['key'] ?? ''),
            'mixUrl' => $choir->mix_key !== null ? Storage::disk('media')->url($choir->mix_key) : null,
            'mixedAt' => $choir->mixed_at?->utc()->toIso8601ZuluString(),
            'voices' => (int) $counts->sum(),
            'minScore' => self::MIN_SCORE,
            'maxVoicesPerPart' => self::MAX_PER_PART,
            'parts' => array_values(array_map(static fn (array $part): array => ['key' => (string) $part['key'], 'name' => (string) ($part['name'] ?? $part['key']), 'voices' => (int) ($counts[$part['key']] ?? 0)], array_filter((array) ($data['parts'] ?? []), static fn (mixed $p): bool => is_array($p) && is_string($p['key'] ?? null)))),
            'mine' => $mine !== null && $mine->hidden_at === null ? [
                'id' => $mine->uuid,
                'partKey' => $mine->part_key,
                'status' => $mine->status,
                'inMix' => $mine->in_mix_at !== null,
                'credit' => $mine->credit,
                'url' => $mine->status === ChoirVoice::READY && $mine->file_key !== null ? Storage::disk('private')->temporaryUrl($mine->file_key, now()->addHours(2)) : null,
                'sampleRate' => $mine->sample_rate,
                'frames' => $mine->frames,
                'offsetFrames' => $mine->offset_frames,
            ] : null,
        ];
    }

    /**
     * choirs.voices.list: who sings in it, newest first.
     *
     * @return array{data: list<array<string, mixed>>, meta: array{nextCursor: string|null}}
     */
    public function voices(string $song, ?string $part): array
    {
        $choir = $this->choirFor($song);
        /** @var CursorPaginator<int, ChoirVoice> $page */
        $page = self::heard($choir)->with('user')
            ->when($part !== null, fn ($query) => $query->where('part_key', $part))
            ->orderByDesc('id')->cursorPaginate(50);

        return [
            'data' => array_values(array_map(static fn (ChoirVoice $voice): array => [
                'id' => $voice->uuid,
                'partKey' => $voice->part_key,
                'singer' => $voice->credit === ChoirVoice::ANONYMOUS ? ['handle' => null, 'name' => __('sharing.a_singer'), 'avatarUrl' => null] : GalleryViews::person($voice->user),
                'createdAt' => $voice->created_at?->utc()->toIso8601ZuluString(),
            ], $page->items())),
            'meta' => ['nextCursor' => $page->nextCursor()?->encode()],
        ];
    }

    /**
     * choirs.join: the caller's take, checked, copied in by ProcessChoirVoice. Joining again
     * replaces their voice.
     *
     * @param  array{takeId: string, partKey: string, score: int, credit: string}  $data
     */
    public function join(User $user, string $song, array $data, ?string $ip): ChoirVoice
    {
        if (! $this->isOpen()) {
            throw new Refusal(409, 'choir_closed');
        }

        $choir = $this->choirFor($song);
        Gallery::ensureAdult($user);

        if ($this->trust->of($user) < TrustLevel::VERIFIED || $user->isSuspended()) {
            throw new Refusal(403, 'email_unverified');
        }

        $take = Take::query()->with(['part', 'project'])->where('uuid', $data['takeId'])->where('status', Take::READY)->whereNull('binned_at')->first();

        if ($take === null || $take->project->user_id !== $user->id || ($take->recorded_by !== null && $take->recorded_by !== $user->id) || $take->file_key === null) {
            throw new Refusal(404, 'not_found');
        }

        $songData = $choir->template->currentVersion->data ?? [];
        $partKeys = array_column(array_filter((array) ($songData['parts'] ?? []), 'is_array'), 'key');

        if ($take->project->template_slug !== $song || $take->part?->template_part_key !== $data['partKey'] || ! in_array($data['partKey'], $partKeys, true)) {
            throw ValidationException::withMessages(['takeId' => __('validation.choir_take')]);
        }

        if (abs((float) $take->project->bpm - (float) ($songData['tempo'] ?? 0)) > 0.01 || $take->project->music_key !== ($songData['key'] ?? null)) {
            throw new Refusal(409, 'key_or_tempo');
        }

        $this->checkScore($take, (int) $data['score']);

        /** @var array{0: ChoirVoice, 1: string|null} $made */
        $made = DB::transaction(function () use ($choir, $user, $take, $data, $ip): array {
            // The song's first voice makes its choir (all the checks have passed).
            if (! $choir->exists) {
                $template = $choir->template;
                $choir = Choir::query()->firstOrCreate(['template_id' => $template->id]);
                $choir->setRelation('template', $template);
            }

            Choir::query()->whereKey($choir->id)->lockForUpdate()->first(['id']);
            $old = ChoirVoice::query()->where('choir_id', $choir->id)->where('user_id', $user->id)->lockForUpdate()->first();

            // A voice Hillel took out stays out: joining again doesn't bring it back.
            if ($old?->hidden_at !== null) {
                throw new Refusal(404, 'not_found');
            }

            $held = $old?->held_at;
            $old?->delete();

            $voice = new ChoirVoice;
            $voice->forceFill([
                'uuid' => (string) Str::uuid(),
                'choir_id' => $choir->id,
                'user_id' => $user->id,
                'take_id' => $take->id,
                'part_key' => $data['partKey'],
                'status' => ChoirVoice::PROCESSING,
                'credit' => $data['credit'],
                'score' => (int) $data['score'],
                'sample_rate' => $take->sample_rate,
                'frames' => $take->frames,
                'offset_frames' => $take->offset_frames + (int) $take->part?->nudge_frames,
                // What they trimmed off stays out of the mix.
                'trim_start_frames' => (int) $take->part?->trim_start_frames,
                'trim_end_frames' => (int) $take->part?->trim_end_frames,
                // A voice held by reports stays held when it's replaced, until Hillel looks.
                'held_at' => $held,
                'rights_confirmed_at' => now(),
            ])->save();
            AuditLog::record('choirs.joined', $voice, $user, after: ['song' => $choir->template->slug, 'part' => $data['partKey']], ip: $ip);

            return [$voice, $old?->file_key];
        });

        [$voice, $oldFile] = $made;

        if ($oldFile !== null) {
            Storage::disk('private')->delete($oldFile);
        }

        ProcessChoirVoice::dispatch($voice->id);

        return $voice;
    }

    /**
     * The stored score wins: a take's own report (kept under its id) says how it sang, and a
     * clipped or bleeding take isn't one to join with.
     */
    private function checkScore(Take $take, int $sent): void
    {
        $stored = Score::query()->where('take_uuid', $take->uuid)->latest('id')->value('summary');
        $summary = is_array($stored) ? $stored : [];
        $pitch = $summary['pitchPercent'] ?? null;

        if (($summary['clipping'] ?? false) === true || ($summary['bleed'] ?? false) === true) {
            throw ValidationException::withMessages(['takeId' => __('validation.choir_quality')]);
        }

        // A take with its own report but no pitch on it (silent, or nothing to score) can't join.
        if ($stored !== null && ! is_numeric($pitch)) {
            throw ValidationException::withMessages(['score' => __('validation.choir_score', ['min' => self::MIN_SCORE])]);
        }

        if (is_numeric($pitch) && abs((float) $pitch - $sent) > 1) {
            throw ValidationException::withMessages(['score' => __('validation.choir_score_mismatch')]);
        }

        if ((is_numeric($pitch) ? (float) $pitch : $sent) < self::MIN_SCORE) {
            throw ValidationException::withMessages(['score' => __('validation.choir_score', ['min' => self::MIN_SCORE])]);
        }
    }

    /**
     * Their voice out, whatever it is now (held, taken out, the choirs closed or the song gone):
     * a singer can always take their own recording back.
     */
    public function leave(User $user, string $song): void
    {
        $voice = ChoirVoice::query()->where('user_id', $user->id)->whereHas('choir.template', fn ($t) => $t->where('slug', $song))->first();

        if ($voice !== null) {
            $this->remove($voice);
        }
    }

    /**
     * Its file and row go, and the next mix drops it.
     */
    public function remove(ChoirVoice $voice): void
    {
        $file = DB::transaction(function () use ($voice): ?string {
            $locked = ChoirVoice::query()->whereKey($voice->id)->lockForUpdate()->first();
            $locked?->delete();

            return $locked?->file_key;
        });

        if ($file !== null) {
            Storage::disk('private')->delete($file);
        }

        Choir::query()->whereKey($voice->choir_id)->update(['changed_at' => now()]);
    }

    /**
     * Every voice this person sang goes (their account is being deleted).
     */
    public function removeFor(User $user): void
    {
        foreach (ChoirVoice::query()->where('user_id', $user->id)->get() as $voice) {
            $this->remove($voice);
        }
    }

    /**
     * admin.choirs.voices.remove: out of the choir, the singer told why (an appeal can bring it
     * back, so its file stays until they leave or their account goes).
     */
    public function takeOut(string $uuid, User $admin, string $note, ?string $ip): void
    {
        $voice = ChoirVoice::query()->with('user')->where('uuid', $uuid)->first() ?? throw new Refusal(404, 'not_found');

        if ($voice->hidden_at === null) {
            $voice->forceFill(['hidden_at' => now(), 'held_at' => null])->save();
            Choir::query()->whereKey($voice->choir_id)->update(['changed_at' => now()]);
            $this->notices->give($voice->user, Notice::CHOIR_VOICE_REMOVED, $voice, 'community_guidelines', $note);
        }

        AuditLog::record('choirs.voice_removed', $voice, $admin, reason: $note, ip: $ip);
    }

    /**
     * admin.choirs.voices.approve: a voice held by reports goes back in, its reports answered.
     */
    public function approve(string $uuid, User $admin, ?string $ip): void
    {
        $voice = ChoirVoice::query()->where('uuid', $uuid)->whereNotNull('held_at')->first() ?? throw new Refusal(404, 'not_found');
        $voice->forceFill(['held_at' => null])->save();
        Report::query()->whereMorphedTo('reportable', $voice)->where('status', Report::OPEN)->update(['status' => Report::DISMISSED, 'resolved_by' => $admin->id, 'resolved_at' => now(), 'updated_at' => now()]);
        Choir::query()->whereKey($voice->choir_id)->update(['changed_at' => now()]);
        AuditLog::record('choirs.voice_approved', $voice, $admin, ip: $ip);
    }

    /**
     * admin.choirs.voices.held: voices held by reports, oldest first, for Hillel to hear.
     *
     * @return list<array<string, mixed>>
     */
    public function held(): array
    {
        $voices = ChoirVoice::query()->with(['user', 'choir.template'])->whereNotNull('held_at')->whereNull('hidden_at')->orderBy('held_at')->limit(100)->get();
        $reports = Report::query()->where('reportable_type', (new ChoirVoice)->getMorphClass())->whereIn('reportable_id', $voices->pluck('id'))->where('status', Report::OPEN)->get()->groupBy('reportable_id');

        return array_values($voices->map(static fn (ChoirVoice $v): array => [
            'id' => $v->uuid,
            'partKey' => $v->part_key,
            'singer' => [...GalleryViews::person($v->user), 'id' => $v->user->sqid()],
            'song' => ['id' => $v->choir->template->slug, 'title' => $v->choir->template->title],
            'url' => $v->file_key !== null ? Storage::disk('private')->temporaryUrl($v->file_key, now()->addHours(2)) : null,
            'heldAt' => $v->held_at?->utc()->toIso8601ZuluString(),
            'reports' => [
                'count' => ($reports->get($v->id) ?? collect())->count(),
                'reasons' => array_values(($reports->get($v->id) ?? collect())->pluck('reason')->unique()->sort()->values()->all()),
            ],
        ])->all());
    }

    /**
     * An appeal won (Notices): it's back in the next mix.
     */
    public function restore(ChoirVoice $voice): void
    {
        $voice->forceFill(['hidden_at' => null])->save();
        Choir::query()->whereKey($voice->choir_id)->update(['changed_at' => now()]);
    }
}
