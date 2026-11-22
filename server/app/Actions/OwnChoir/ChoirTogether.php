<?php

declare(strict_types=1);

namespace App\Actions\OwnChoir;

use App\Actions\Counts\RecordCounts;
use App\Actions\Projects\Owned;
use App\Actions\Sharing\DeleteHarmony;
use App\Enums\OwnChoirStatus;
use App\Exceptions\Refusal;
use App\Models\AuditLog;
use App\Models\OwnChoirInvite;
use App\Models\OwnChoirMember;
use App\Models\OwnChoirPart;
use App\Models\OwnChoirRequest;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Models\UserLearning;
use App\Support\Flags;
use App\Support\Gallery\Gallery;
use App\Support\Http\Hosts;
use App\Support\Ids;
use App\Support\OwnChoir\ChoirAssign;
use App\Support\OwnChoir\ClipTune;
use App\Support\OwnChoir\OwnChoir;
use App\Support\Storage\Uploads;
use App\Support\Trust\TrustLevel;
use App\Support\Usage\UploadBytes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A choir with friends (docs/choir-together-plan.md, behind own_choir_together, per account): a ready song's owner
 * invites a friend; the friend agrees and gives a clip or their saved voice; the parts their voice suits better
 * (ChoirAssign) are made again in it, as a job on the owner's song charged to the owner, the owner's own render kept
 * and playing again whenever the friend's goes. The friend's voice sings only their parts in that one song; the owner
 * never gets it.
 */
final class ChoirTogether
{
    public const FLAG = 'own_choir_together';

    public function __construct(private readonly Uploads $uploads) {}

    public static function on(?User $user): bool
    {
        $flags = app(Flags::class);

        return OwnChoir::on($user) && $flags->exists(self::FLAG) && $flags->onFor(self::FLAG, $user);
    }

    public static function ensureOn(?User $user): void
    {
        if (! self::on($user)) {
            throw new Refusal(404, 'not_found');
        }
    }

    /**
     * ownChoir.invites.create.
     *
     * @param  list<string>|null  $parts  the parts the invite names (the song's own); by default those that sit high or
     *                                    low for the owner
     * @return array{invite: OwnChoirInvite, url: string}
     */
    public function invite(User $owner, OwnChoirRequest $song, ?array $parts = null): array
    {
        self::ensureOn($owner);
        self::ensureOwner($owner, $song);

        if ($song->status !== OwnChoirStatus::Ready) {
            throw new Refusal(409, 'not_ready');
        }

        $hint = self::inviteHint($song, $owner) ?? throw new Refusal(404, 'not_found');

        if (! $hint['canInvite'] && $hint['reason'] === 'plus_required') {
            throw new Refusal(403, 'plus_required');
        }

        if ($hint['left'] === 0 && $hint['reason'] === 'full') {
            throw new Refusal(409, 'choir_full');
        }

        $today = OwnChoirInvite::query()->whereIn('own_choir_request_id', OwnChoirRequest::query()->select('id')->where('user_id', $owner->id))->where('created_at', '>', now()->subDay())->count();

        if ($today >= (int) config('own_choir.together.invites_a_day')) {
            throw new Refusal(429, 'rate_limited', ['retryAfter' => 3600], ['Retry-After' => '3600']);
        }

        $named = $parts ?? $hint['parts'];

        if ($named === [] || array_diff($named, array_keys(self::partNotes($song))) !== []) {
            throw ValidationException::withMessages(['parts' => __('validation.choir_part')]);
        }

        // Long enough that error reports mask it (ScrubEvent masks 32 and up).
        $token = Str::random(32);
        $invite = new OwnChoirInvite;
        $invite->forceFill(['own_choir_request_id' => $song->id, 'token_hash' => hash('sha256', $token), 'parts' => array_values(array_unique($named)), 'expires_at' => now()->addDays((int) config('own_choir.together.invite_days'))])->save();
        app(RecordCounts::class)->server('own_choir_invite_made', true, null);

        // On the app's host where it has one, else this one (a whole address either way: it's shared as a link).
        $link = Hosts::app('/choir/join/'.$token);

        return ['invite' => $invite, 'url' => str_starts_with($link, '/') ? url($link) : $link];
    }

    /** ownChoir.invites.delete: the owner takes back an invite not yet used. */
    public function revoke(User $owner, OwnChoirInvite $invite): void
    {
        self::ensureOn($owner);
        self::ensureOwner($owner, $invite->song);

        if ($invite->used_at !== null) {
            throw new Refusal(404, 'not_found');
        }

        $invite->forceFill(['revoked_at' => now()])->save();
    }

    /**
     * ownChoir.invites.show: the invite as the friend opening it sees it.
     *
     * @return array<string, mixed>
     */
    public function show(string $token, ?User $viewer = null, ?string $partKey = null, bool $savedVoice = false, int $octave = 0, ?string $clipTemplateId = null): array
    {
        $invite = self::byToken($token);
        $song = $invite->song;
        $status = $invite->status();
        ['words' => $words, 'sha' => $sha] = self::words($song);
        app(RecordCounts::class)->server('own_choir_invite_opened', auth()->check(), null);

        // A link that's no longer open names nothing: it may have gone round (the review).
        if ($status !== 'open') {
            return ['status' => $status, 'title' => null, 'templateId' => null, 'owner' => null, 'parts' => [], 'yourParts' => null, 'listen' => [], 'words' => null, 'wordsSha' => null, 'expiresAt' => $invite->expires_at->utc()->toIso8601ZuluString()];
        }

        return [
            'status' => $status,
            'title' => (string) $song->templateVersion?->template?->title,
            'templateId' => (string) $song->templateVersion?->template?->slug,
            'owner' => self::firstName($song->user),
            'parts' => array_values($invite->parts),
            // Exactly what this person's voice would sing, for their yes (null while it can't be told).
            'yourParts' => $viewer === null || $viewer->id === $song->user_id ? null : self::assignFor($invite, $viewer, $savedVoice ? null : $partKey, $octave, $clipTemplateId),
            'listen' => array_values(self::activeParts($song)->map(static fn (OwnChoirPart $p): array => OwnChoir::recording($p))->all()),
            'words' => $words,
            'wordsSha' => $sha,
            'expiresAt' => $invite->expires_at->utc()->toIso8601ZuluString(),
        ];
    }

    /**
     * ownChoir.invites.accept: the friend joins.
     *
     * @param  array{parts?: list<string>, octave?: int|null, clipTemplateId?: string|null, clipTranspose?: int|null, showName: bool, wordsSha?: string|null, useSavedVoice?: bool|null, partKey?: string|null, offsetFrames?: int|null, sampleRate?: int|null, frames?: int|null, bytes?: int|null, sha256?: string|null}  $data
     * @return array{member: OwnChoirMember, put: array{url: string, method: string, headers: array<string, string>, expiresAt: \DateTimeInterface}|null}
     */
    public function accept(User $user, string $token, array $data): array
    {
        self::ensureOn($user);
        $invite = self::byToken($token);
        $song = $invite->song;
        $owner = $song->user;

        if ($invite->status() !== 'open') {
            throw new Refusal(409, 'invite_closed');
        }

        if ($owner->id === $user->id) {
            throw new Refusal(409, 'own_song');
        }

        if (OwnChoirMember::query()->where('own_choir_request_id', $song->id)->where('user_id', $user->id)->whereNull('left_at')->exists()) {
            throw new Refusal(409, 'already_member');
        }

        if (! OwnChoir::consented($user)) {
            throw new Refusal(403, 'consent_required');
        }

        Gallery::ensureAdult($user);

        if (app(TrustLevel::class)->of($user->fresh() ?? $user) < TrustLevel::VERIFIED) {
            throw new Refusal(403, 'email_unverified');
        }

        if (OwnChoirMember::query()->where('user_id', $user->id)->where('joined_at', '>', now()->subDay())->count() >= (int) config('own_choir.together.joins_a_day')) {
            throw new Refusal(429, 'rate_limited', ['retryAfter' => 3600], ['Retry-After' => '3600']);
        }

        ['sha' => $sha] = self::words($song);

        if ($sha !== null && ($data['wordsSha'] ?? null) !== $sha) {
            throw new Refusal(409, 'words_changed');
        }

        $voice = ($data['useSavedVoice'] ?? false) === true ? OwnChoir::voice($user) : null;
        $withClip = isset($data['sampleRate'], $data['frames'], $data['bytes'], $data['sha256'], $data['partKey']);

        if (! $withClip && $voice === null) {
            throw new Refusal(409, 'voice_required');
        }

        $notes = self::partNotes($song);

        if ($withClip && ! isset($notes[(string) $data['partKey']])) {
            throw new Refusal(404, 'not_found');
        }

        // Their yes is to exact parts: what their voice sings now, among the invite's named ones.
        $agreed = array_values(array_map('strval', (array) ($data['parts'] ?? [])));
        // A new clip is the tune, sung in the octave the app measured on the device before the yes.
        // The octave sung, and the semitones the tune was moved to their first note (2026-11-23).
        $octave = $withClip ? (int) ($data['octave'] ?? 0) : 0;
        $ranges = self::ranges($invite, $user, $withClip ? (string) $data['partKey'] : null, $octave + ($withClip ? (int) ($data['clipTranspose'] ?? 0) : 0), $data['clipTemplateId'] ?? null) ?? throw new Refusal(409, 'voice_required');
        $tune = $withClip ? ClipTune::version($data['clipTemplateId'] ?? null, $song->templateVersion ?? throw new \LogicException('A song has its version.')) : null;
        $assigned = self::assignWith($invite, $user, $ranges);

        if ($agreed !== $assigned) {
            throw new Refusal(409, 'parts_changed');
        }

        $clip = $data;

        return DB::transaction(function () use ($user, $song, $owner, $invite, $clip, $voice, $withClip, $sha, $ranges, $octave, $tune): array {
            // The owner's account locked: their minutes and their friends are read and spent one join at a time.
            Owned::lockAccount($owner);
            OwnChoirRequest::query()->whereKey($song->id)->lockForUpdate()->first();
            $locked = OwnChoirInvite::query()->whereKey($invite->id)->lockForUpdate()->first();

            if ($locked === null || $locked->status() !== 'open') {
                throw new Refusal(409, 'invite_closed');
            }

            // The cap, under the lock: a free song's one friend, Plus's 7, never more than the parts minus one.
            ['limit' => $limit, 'withPlus' => $withPlus] = self::caps($song, $owner);

            if (self::activeMembers($song)->count() >= $limit) {
                throw $limit < $withPlus ? new Refusal(403, 'plus_required') : new Refusal(409, 'choir_full');
            }

            // Rejoining after leaving: the old row (kept as the record of their leaving) makes way.
            OwnChoirMember::query()->where('own_choir_request_id', $song->id)->where('user_id', $user->id)->whereNotNull('left_at')->delete();

            // Read again under the song's lock: a friend who joined a moment ago took what they took.
            $parts = self::assignWith($locked, $user, $ranges);
            $agreed = array_values(array_map('strval', (array) ($clip['parts'] ?? [])));

            if ($parts !== $agreed) {
                throw new Refusal(409, 'parts_changed');
            }

            $member = new OwnChoirMember;
            $member->forceFill(['own_choir_request_id' => $song->id, 'user_id' => $user->id, 'name' => mb_substr(self::firstName($user), 0, 80), 'parts' => $parts, 'consent_version' => OwnChoir::termsVersion(), 'words_sha' => $sha, 'show_name' => (bool) $clip['showName'], 'joined_at' => now()])->save();
            $locked->forceFill(['used_at' => now(), 'used_by_member_id' => $member->id])->save();
            app(RecordCounts::class)->server('own_choir_joined', true, null);
            // The record of their yes, kept after they leave: the terms' version, the words, the exact parts.
            AuditLog::record('own_choir.member_joined', $user, null, after: ['song' => $song->sqid(), 'parts' => $parts, 'termsVersion' => OwnChoir::termsVersion(), 'wordsSha' => $sha, 'showName' => (bool) $clip['showName']]);

            if ($parts === []) {
                return ['member' => $member, 'put' => null];
            }

            $data = (array) $song->templateVersion?->data;
            $data['parts'] = array_values(array_filter((array) ($data['parts'] ?? []), static fn (mixed $p): bool => is_array($p) && in_array($p['key'] ?? null, $parts, true)));
            $minutes = OwnChoir::partMinutes($data);
            $job = new OwnChoirRequest;
            $job->forceFill([
                'user_id' => $user->id,
                'template_version_id' => $song->template_version_id,
                'parent_request_id' => $song->id,
                'own_choir_member_id' => $member->id,
                'bill_to_user_id' => $owner->id,
                'only_parts' => $parts,
                'part_minutes' => $minutes,
                'own' => $song->own,
                'priority' => OwnChoir::tier($owner) === 'paid' ? 1 : 0,
                'held_for_minutes' => ! self::ownerCanPay($owner, $minutes),
            ]);

            if ($voice !== null && ! $withClip) {
                $job->forceFill(['own_choir_voice_id' => $voice->id, 'from_voice' => true, 'status' => OwnChoirStatus::Queued])->save();

                return ['member' => $member, 'put' => null];
            }

            // A new clip: as requests.create takes one (its bytes the friend's own), checked once it's in.
            app(UploadBytes::class)->count($user, (int) $clip['bytes']);
            $job->forceFill([
                // The tune, whatever part was named (as every choir clip), and the octave the app measured.
                'part_key' => ($tune !== null ? ClipTune::melody($tune) : null) ?? (string) $clip['partKey'],
                'clip_template_version_id' => $tune?->id,
                'clip_transpose' => (int) ($clip['clipTranspose'] ?? 0),
                'offset_frames' => $clip['offsetFrames'] ?? null,
                'octave' => $octave,
                'status' => OwnChoirStatus::Uploading,
                'upload_key' => 'uploads/choir/'.Str::lower(Str::random(32)).'.wav',
                'sample_rate' => $clip['sampleRate'],
                'frames' => $clip['frames'],
                'bytes' => $clip['bytes'],
                'sha256' => strtolower((string) $clip['sha256']),
            ])->save();

            return ['member' => $member, 'put' => $this->uploads->presign((string) $job->upload_key, 'audio/wav', (int) $job->bytes)];
        });
    }

    /**
     * ownChoir.members.complete: the friend's clip is in; it's checked, then their parts are queued.
     */
    public function complete(User $user, OwnChoirMember $member): OwnChoirMember
    {
        self::ensureOn($user);

        if ($member->user_id !== $user->id) {
            throw new Refusal(404, 'not_found');
        }

        $job = $member->job;

        if ($job !== null && $job->status === OwnChoirStatus::Uploading) {
            app(OwnChoirRequests::class)->complete($user, $job);
        }

        return $member->refresh();
    }

    /** ownChoir.members.delete: a friend leaves, or the owner removes them. */
    public function leave(User $user, OwnChoirMember $member): void
    {
        // Whatever the switch: a singer can always leave what they're in, and an owner remove them (the review).
        if ($member->left_at !== null || ($member->user_id !== $user->id && $member->song->user_id !== $user->id)) {
            throw new Refusal(404, 'not_found');
        }

        self::removeMember($member, $member->user_id === $user->id ? 'left' : 'removed', $user);
    }

    /**
     * Their part in the song ends: their renders' files go (the rows stay, emptied, still counting toward the owner's
     * minutes), every share of the owner's that held their parts comes down, and they're kept as having left (the
     * owner's notice, and the record of it). The owner's own render plays again.
     */
    public static function removeMember(OwnChoirMember $member, string $reason, ?User $by = null): void
    {
        foreach (OwnChoirRequest::query()->where('own_choir_member_id', $member->id)->get() as $job) {
            OwnChoirRequests::purgeJob($job);
        }

        app(DeleteHarmony::class)->withFriend($member->id);
        $member->forceFill(['left_at' => now(), 'left_reason' => $reason])->save();
        AuditLog::record('own_choir.member_'.($reason === 'removed' ? 'removed' : 'left'), $by, null, after: ['song' => Ids::encode($member->own_choir_request_id), 'member' => $member->sqid(), 'parts' => $member->parts, 'reason' => $reason]);
    }

    /**
     * A song's friends still in it.
     *
     * @return Builder<OwnChoirMember>
     */
    public static function activeMembers(OwnChoirRequest $song): Builder
    {
        return OwnChoirMember::query()->where('own_choir_request_id', $song->id)->whereNull('left_at');
    }

    /**
     * How many friends a song may have: a free song's one, Plus's 7, never more than its parts minus one.
     *
     * @return array{limit: int, withPlus: int}
     */
    private static function caps(OwnChoirRequest $song, User $owner): array
    {
        $parts = count(self::partNotes($song)) - 1;
        $free = OwnChoir::tier($owner) === 'free';

        return ['limit' => min($parts, (int) config($free ? 'own_choir.together.free_friends' : 'own_choir.together.plus_friends')), 'withPlus' => min($parts, (int) config('own_choir.together.plus_friends'))];
    }

    /**
     * Everyone's part in a song that's going (the owner deleted it, or their account): the friends' parts, and every
     * share of a friend's that held the owner's parts (labelled as theirs).
     */
    public static function songGone(OwnChoirRequest $song): void
    {
        foreach (self::activeMembers($song)->get() as $member) {
            self::removeMember($member, 'song_deleted');
        }

        app(DeleteHarmony::class)->withOwnersParts($song->id);
        OwnChoirInvite::query()->where('own_choir_request_id', $song->id)->delete();
    }

    /** A singer's part in every song they joined (their voice deleted, everything forgotten, the account gone). */
    public static function leaveAll(User $user): void
    {
        foreach (OwnChoirMember::query()->where('user_id', $user->id)->whereNull('left_at')->get() as $member) {
            self::removeMember($member, 'voice_deleted', $user);
        }
    }

    /** Friends' parts waiting for their owner's minutes: on their way once the owner has them. Hourly. */
    public static function release(): int
    {
        $released = 0;

        foreach (OwnChoirRequest::query()->with('parent.user')->where('held_for_minutes', true)->where('status', OwnChoirStatus::Queued->value)->get() as $job) {
            $owner = $job->parent?->user;

            if ($owner !== null && self::ownerCanPay($owner, $job->part_minutes)) {
                $job->forceFill(['held_for_minutes' => false])->save();
                $released++;
            }
        }

        return $released;
    }

    /**
     * The owner can pay for a friend's parts: paid Plus by its part-minutes, Plus not paid for by a song left this month
     * (a friend's parts are one); the free song includes its one friend (the cap keeps it to one).
     */
    public static function ownerCanPay(User $owner, float $minutes): bool
    {
        $quota = OwnChoir::quota($owner);

        return match (true) {
            $quota['unit'] === 'minutes' => ($quota['minutesLeft'] ?? 0.0) >= $minutes,
            $quota['plus'] => $quota['monthLeft'] > 0,
            default => true,
        };
    }

    /**
     * Each part's active render: the owner's, overridden by a friend's ready one.
     *
     * @return Collection<int, OwnChoirPart>
     */
    public static function activeParts(OwnChoirRequest $song): Collection
    {
        $active = $song->parts()->where('status', 'ready')->whereNotNull('media_key')->get()->keyBy('part_key');

        foreach (OwnChoirRequest::query()->with('parts')->where('parent_request_id', $song->id)->where('status', OwnChoirStatus::Ready->value)->get() as $job) {
            foreach ($job->parts->where('status', 'ready')->whereNotNull('media_key') as $part) {
                $active[$part->part_key] = $part;
            }
        }

        $order = array_keys(self::partNotes($song));

        return $active->sortBy(static fn (OwnChoirPart $p): int => (int) array_search($p->part_key, $order, true))->values();
    }

    /**
     * Who sings each part now, as $viewer sees it (contract ownChoir.json `voices`).
     *
     * @return list<array<string, mixed>>
     */
    public static function voices(OwnChoirRequest $song, User $viewer): array
    {
        // With the switch on, or whenever friends are in it (a singer can always see what they're in).
        if ($song->status !== OwnChoirStatus::Ready || (! self::on($viewer) && ! self::activeMembers($song)->exists())) {
            return [];
        }

        $notes = self::partNotes($song);
        $owner = $song->user_id === $viewer->id;
        $ownerRange = $owner ? self::ownerRange($song, $notes) : null;
        $members = self::activeMembers($song)->with(['user', 'job.parts'])->get();
        $voices = [];

        foreach ($notes as $key => $partNotes) {
            $member = $members->first(static fn (OwnChoirMember $m): bool => in_array($key, $m->parts, true));
            $job = $member?->job;
            $ready = $job !== null && $job->status === OwnChoirStatus::Ready && $job->parts->where('part_key', $key)->where('status', 'ready')->isNotEmpty();
            $mine = $member !== null && $member->user_id === $viewer->id;

            $voices[] = [
                'partKey' => (string) $key,
                'whose' => $member === null ? ($owner ? 'you' : 'owner') : ($mine ? 'you' : ($owner ? 'friend' : 'owner')),
                'name' => $member === null ? ($owner ? null : self::firstName($song->user)) : ($mine ? null : ($owner ? self::firstName($member->user) : self::firstName($song->user))),
                'memberId' => $member?->sqid(),
                'status' => $member === null || $ready ? 'ready' : ($job?->held_for_minutes === true ? 'waiting_minutes' : 'on_its_way'),
                'fit' => $owner && $ownerRange !== null ? ChoirAssign::lean($partNotes, $ownerRange) : null,
            ];
        }

        return $voices;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function members(OwnChoirRequest $song, User $viewer): array
    {
        if ($song->user_id !== $viewer->id) {
            return [];
        }

        return array_values(self::activeMembers($song)->with(['user', 'job', 'song.user'])->orderBy('id')->get()->map(static fn (OwnChoirMember $m): array => self::member($m))->all());
    }

    /**
     * Friends who left (or were removed, or deleted their voice or account): the owner's notice until seen.
     *
     * @return list<array{name: string, at: string, seen: bool}>
     */
    public static function left(OwnChoirRequest $song, User $viewer): array
    {
        if ($song->user_id !== $viewer->id) {
            return [];
        }

        return array_values(OwnChoirMember::query()->where('own_choir_request_id', $song->id)->whereNotNull('left_at')->orderBy('left_at')->get()
            ->map(static fn (OwnChoirMember $m): array => ['name' => $m->name, 'at' => $m->left_at?->utc()->toIso8601ZuluString() ?? '', 'seen' => $m->owner_seen_at !== null])->all());
    }

    /**
     * @return array<string, mixed>
     */
    public static function member(OwnChoirMember $member): array
    {
        $job = $member->job;

        return [
            'id' => $member->sqid(),
            'requestId' => Ids::encode($member->own_choir_request_id),
            'name' => $member->name !== '' ? $member->name : self::firstName($member->user),
            'parts' => array_values($member->parts),
            'status' => match (true) {
                $member->parts === [] => 'ready',
                $job === null => 'failed',
                $job->status === OwnChoirStatus::Uploading => 'uploading',
                $job->status === OwnChoirStatus::Ready => 'ready',
                $job->status === OwnChoirStatus::Failed => 'failed',
                $job->held_for_minutes => 'waiting_minutes',
                default => 'on_its_way',
            },
            'showName' => $member->show_name,
            'joinedAt' => $member->joined_at->utc()->toIso8601ZuluString(),
            'seen' => $member->seen_at !== null,
            'minutesShort' => $job !== null && $job->held_for_minutes ? max(0.0, round($job->part_minutes - (float) (OwnChoir::quota($member->song->user)['minutesLeft'] ?? 0.0), 1)) : null,
        ];
    }

    /**
     * The owner's invite line on a ready song (contract ownChoir.json `invite`), or null.
     *
     * @return array{canInvite: bool, parts: list<string>, left: int, reason: string|null}|null
     */
    public static function inviteHint(OwnChoirRequest $song, User $owner): ?array
    {
        if (! self::on($owner) || $song->user_id !== $owner->id || $song->parent_request_id !== null || $song->status !== OwnChoirStatus::Ready) {
            return null;
        }

        $notes = self::partNotes($song);
        $range = self::ownerRange($song, $notes);
        $leaning = $range === null ? [] : array_values(array_map('strval', array_keys(array_filter($notes, static fn (array $n): bool => ChoirAssign::lean($n, $range) !== null))));
        $free = OwnChoir::tier($owner) === 'free';
        $limit = min(count($notes) - 1, (int) config($free ? 'own_choir.together.free_friends' : 'own_choir.together.plus_friends'));
        $withPlus = min(count($notes) - 1, (int) config('own_choir.together.plus_friends'));
        // Friends in it, and invites still open: each open link is a place a friend may take.
        $have = self::activeMembers($song)->count() + OwnChoirInvite::query()->where('own_choir_request_id', $song->id)->whereNull('used_at')->whereNull('revoked_at')->where('expires_at', '>', now())->count();
        $left = max(0, $limit - $have);
        $reason = match (true) {
            $left > 0 && $leaning === [] => 'all_fit',
            $left > 0 => null,
            $free && $have < $withPlus => 'plus_required',
            default => 'full',
        };

        return ['canInvite' => $left > 0 && $leaning !== [], 'parts' => $leaning, 'left' => $left, 'reason' => $reason];
    }

    /**
     * The songs this person sings in (contract ownChoir.json `joined`).
     *
     * @return list<array{member: array<string, mixed>, song: array<string, mixed>}>
     */
    public static function joined(User $user): array
    {
        // Whatever the switch: a singer can always see the choirs they're in.
        return array_values(OwnChoirMember::query()->with(['user', 'job', 'song.templateVersion.template', 'song.user'])->where('user_id', $user->id)->whereNull('left_at')->latest('joined_at')->get()
            ->filter(static fn (OwnChoirMember $m): bool => $m->song->status !== OwnChoirStatus::Deleted)
            ->map(static fn (OwnChoirMember $m): array => ['member' => self::member($m), 'song' => OwnChoir::request($m->song, false, $user)])->all());
    }

    /**
     * A song's parts' notes by key, in its order.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public static function partNotes(OwnChoirRequest $song): array
    {
        return $song->templateVersion === null ? [] : self::versionNotes($song->templateVersion);
    }

    /**
     * Each part's guide notes in a template version, by key.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public static function versionNotes(TemplateVersion $version): array
    {
        $notes = [];

        foreach ((array) ($version->data['parts'] ?? []) as $part) {
            if (is_array($part) && is_string($part['key'] ?? null)) {
                $notes[$part['key']] = array_values(array_filter((array) ($part['guide']['notes'] ?? []), 'is_array'));
            }
        }

        return $notes;
    }

    /**
     * A singer's range: their measured range from the range check, or the part they sang along to widened two
     * semitones (a clip's part, or the one their saved voice was made from).
     *
     * @param  array<string, list<array<string, mixed>>>  $notes
     * @return array{0: int, 1: int}|null
     */
    public static function range(User $user, ?string $sangAlong, array $notes, int $octave = 0): ?array
    {
        $progress = (array) (UserLearning::query()->where('user_id', $user->id)->first()->progress ?? []);
        $range = $progress['range'] ?? null;

        if (($progress['rangeSource'] ?? null) === 'measured' && is_array($range) && is_numeric($range['low'] ?? null) && is_numeric($range['high'] ?? null)) {
            return [(int) $range['low'], (int) $range['high']];
        }

        // The part they sang (the tune, since 2026-11-23), in the octave they sang it.
        if ($sangAlong !== null && isset($notes[$sangAlong])) {
            $range = ChoirAssign::fromPart($notes[$sangAlong]);

            return $range === null ? null : [$range[0] + $octave, $range[1] + $octave];
        }

        // Their saved voice: the notes it actually sang (the worker's report), widened two semitones.
        $voice = OwnChoir::voice($user);

        if ($voice !== null && $voice->sung_low !== null && $voice->sung_high !== null) {
            return [$voice->sung_low - 2, $voice->sung_high + 2];
        }

        // Older voices: the part they sang along to when they made it, in the octave the worker heard.
        $origin = $voice === null ? null : OwnChoirRequest::query()->with('templateVersion')->where('own_choir_voice_id', $voice->id)->whereNotNull('part_key')->oldest('id')->first();
        // The tune its clip sang (Happy Birthday since 2026-11-23, else the song's own).
        $originTune = $origin?->clipVersion();
        $originNotes = $originTune === null ? [] : self::versionNotes($originTune);

        $range = $origin !== null && isset($originNotes[(string) $origin->part_key]) ? ChoirAssign::fromPart($originNotes[(string) $origin->part_key]) : null;
        $shift = (int) ($voice->octave ?? 0) + (int) ($origin->clip_transpose ?? 0);

        return $range === null ? null : [$range[0] + $shift, $range[1] + $shift];
    }

    /**
     * The parts a person's voice would sing on an invite: some of its named parts, never another (the UX review), those
     * it suits better than the owner's, among those no other friend has; null while their range can't be told.
     *
     * @return list<string>|null
     */
    public static function assignFor(OwnChoirInvite $invite, User $user, ?string $sangAlong, int $octave = 0, ?string $clipTemplateId = null): ?array
    {
        $ranges = self::ranges($invite, $user, $sangAlong, $octave, $clipTemplateId);

        return $ranges === null ? null : self::assignWith($invite, $user, $ranges);
    }

    /**
     * The friend's range and the owner's, read once (accept checks them before its lock and again under it).
     *
     * @return array{friend: array{0: int, 1: int}, owner: array{0: int, 1: int}}|null
     */
    private static function ranges(OwnChoirInvite $invite, User $user, ?string $sangAlong, int $octave = 0, ?string $clipTemplateId = null): ?array
    {
        $notes = self::partNotes($invite->song);
        // A new clip is always a tune (Happy Birthday by default), whatever part was named (2026-11-23): its range is that
        // tune's melody in the octave sung.
        $friend = null;

        if ($sangAlong !== null) {
            $tune = ClipTune::version($clipTemplateId, $invite->song->templateVersion ?? throw new \LogicException('A song has its version.'));
            $friend = self::range($user, ClipTune::melody($tune), self::versionNotes($tune), $octave);
        } else {
            $friend = self::range($user, null, $notes, $octave);
        }
        $owner = self::ownerRange($invite->song, $notes);

        return $friend === null || $owner === null ? null : ['friend' => $friend, 'owner' => $owner];
    }

    /**
     * @param  array{friend: array{0: int, 1: int}, owner: array{0: int, 1: int}}  $ranges
     * @return list<string>
     */
    private static function assignWith(OwnChoirInvite $invite, User $user, array $ranges): array
    {
        $song = $invite->song;
        $notes = self::partNotes($song);
        [$range, $owner] = [$ranges['friend'], $ranges['owner']];

        $taken = self::activeMembers($song)->where('user_id', '!=', $user->id)->pluck('parts')->flatten()->map(static fn (mixed $p): string => (string) $p)->all();
        $taken = array_values($taken);
        $named = array_intersect_key($notes, array_flip($invite->parts));

        return ChoirAssign::forFriend($named, $owner, $range, $taken, count($notes));
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $notes
     * @return array{0: int, 1: int}|null
     */
    private static function ownerRange(OwnChoirRequest $song, array $notes): ?array
    {
        // The owner's clip sang a tune (Happy Birthday since 2026-11-23): their range is its melody, in the octave sung.
        $tune = $song->clipVersion();

        return self::range($song->user, $song->part_key, $tune === null ? $notes : self::versionNotes($tune), (int) ($song->octave ?? 0) + $song->clip_transpose);
    }

    /**
     * An own song's typed words, and their hash (the friend's yes is to these words); null for a library song.
     *
     * @return array{words: string|null, sha: string|null}
     */
    private static function words(OwnChoirRequest $song): array
    {
        if (! $song->own) {
            return ['words' => null, 'sha' => null];
        }

        $lines = [];

        foreach ((array) ($song->templateVersion->data['parts'] ?? []) as $part) {
            $words = trim(implode(' ', array_filter(array_map(static fn (mixed $n): string => is_array($n) ? trim((string) ($n['lyric'] ?? '')) : '', (array) ($part['guide']['notes'] ?? [])))));

            if ($words !== '') {
                $lines[] = ucfirst((string) ($part['key'] ?? '')).': '.$words;
            }
        }

        $words = $lines === [] ? null : implode("\n", $lines);

        return ['words' => $words, 'sha' => $words === null ? null : hash('sha256', $words)];
    }

    private static function byToken(string $token): OwnChoirInvite
    {
        $invite = OwnChoirInvite::query()->with('song.user', 'song.templateVersion.template')->where('token_hash', hash('sha256', $token))->first();

        if ($invite === null || $invite->song->status === OwnChoirStatus::Deleted || ! self::on($invite->song->user)) {
            throw new Refusal(404, 'not_found');
        }

        return $invite;
    }

    private static function ensureOwner(User $owner, OwnChoirRequest $song): void
    {
        if ($song->user_id !== $owner->id || $song->parent_request_id !== null || $song->status === OwnChoirStatus::Deleted) {
            throw new Refusal(404, 'not_found');
        }
    }

    private static function firstName(?User $user): string
    {
        return trim(explode(' ', trim((string) $user?->name))[0] ?? '');
    }
}
