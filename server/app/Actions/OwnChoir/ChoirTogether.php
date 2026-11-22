<?php

declare(strict_types=1);

namespace App\Actions\OwnChoir;

use App\Actions\Counts\RecordCounts;
use App\Enums\OwnChoirStatus;
use App\Exceptions\Refusal;
use App\Models\OwnChoirInvite;
use App\Models\OwnChoirMember;
use App\Models\OwnChoirPart;
use App\Models\OwnChoirRequest;
use App\Models\User;
use App\Models\UserLearning;
use App\Support\Flags;
use App\Support\Gallery\Gallery;
use App\Support\Http\Hosts;
use App\Support\Ids;
use App\Support\OwnChoir\ChoirAssign;
use App\Support\OwnChoir\OwnChoir;
use App\Support\Storage\Uploads;
use App\Support\Trust\TrustLevel;
use App\Support\Usage\UploadBytes;
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

        $token = Str::random(24);
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
    public function show(string $token, ?User $viewer = null, ?string $partKey = null, bool $savedVoice = false): array
    {
        $invite = self::byToken($token);
        $song = $invite->song;
        $status = $invite->status();
        ['words' => $words, 'sha' => $sha] = self::words($song);
        app(RecordCounts::class)->server('own_choir_invite_opened', auth()->check(), null);

        return [
            'status' => $status,
            'title' => (string) $song->templateVersion?->template?->title,
            'templateId' => (string) $song->templateVersion?->template?->slug,
            'owner' => self::firstName($song->user),
            'parts' => array_values($invite->parts),
            // Exactly what this person's voice would sing, for their yes (null while it can't be told).
            'yourParts' => $viewer === null || $status !== 'open' || $viewer->id === $song->user_id ? null : self::assignFor($invite, $viewer, $savedVoice ? null : $partKey),
            'listen' => $status === 'open' ? array_values(self::activeParts($song)->map(static fn (OwnChoirPart $p): array => OwnChoir::recording($p))->all()) : [],
            'words' => $words,
            'wordsSha' => $sha,
            'expiresAt' => $invite->expires_at->utc()->toIso8601ZuluString(),
        ];
    }

    /**
     * ownChoir.invites.accept: the friend joins.
     *
     * @param  array{parts?: list<string>, showName: bool, wordsSha?: string|null, useSavedVoice?: bool|null, partKey?: string|null, offsetFrames?: int|null, sampleRate?: int|null, frames?: int|null, bytes?: int|null, sha256?: string|null}  $data
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

        if (OwnChoirMember::query()->where('own_choir_request_id', $song->id)->where('user_id', $user->id)->exists()) {
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
        $ranges = self::ranges($invite, $user, $withClip ? (string) $data['partKey'] : null) ?? throw new Refusal(409, 'voice_required');
        $assigned = self::assignWith($invite, $user, $ranges);

        if ($agreed !== $assigned) {
            throw new Refusal(409, 'parts_changed');
        }

        $clip = $data;

        return DB::transaction(function () use ($user, $song, $owner, $invite, $clip, $voice, $withClip, $sha, $ranges): array {
            OwnChoirRequest::query()->whereKey($song->id)->lockForUpdate()->first();
            $locked = OwnChoirInvite::query()->whereKey($invite->id)->lockForUpdate()->first();

            if ($locked === null || $locked->status() !== 'open') {
                throw new Refusal(409, 'invite_closed');
            }

            // Read again under the song's lock: a friend who joined a moment ago took what they took.
            $parts = self::assignWith($locked, $user, $ranges);
            $agreed = array_values(array_map('strval', (array) ($clip['parts'] ?? [])));

            if ($parts !== $agreed) {
                throw new Refusal(409, 'parts_changed');
            }

            $member = new OwnChoirMember;
            $member->forceFill(['own_choir_request_id' => $song->id, 'user_id' => $user->id, 'parts' => $parts, 'consent_version' => OwnChoir::termsVersion(), 'words_sha' => $sha, 'show_name' => (bool) $clip['showName'], 'joined_at' => now()])->save();
            $locked->forceFill(['used_at' => now(), 'used_by_member_id' => $member->id])->save();
            app(RecordCounts::class)->server('own_choir_joined', true, null);

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
                'part_key' => (string) $clip['partKey'],
                'offset_frames' => $clip['offsetFrames'] ?? null,
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
        self::ensureOn($user);

        if ($member->user_id !== $user->id && $member->song->user_id !== $user->id) {
            throw new Refusal(404, 'not_found');
        }

        self::removeMember($member);
    }

    /** Their renders in the song deleted (files and rows), the owner's playing again; the member gone. */
    public static function removeMember(OwnChoirMember $member): void
    {
        foreach (OwnChoirRequest::query()->where('own_choir_member_id', $member->id)->get() as $job) {
            OwnChoirRequests::purgeJob($job);
        }

        OwnChoirMember::query()->whereKey($member->id)->delete();
    }

    /** Everyone's part in a song that's going (the owner deleted it, or their account). */
    public static function songGone(OwnChoirRequest $song): void
    {
        foreach (OwnChoirMember::query()->where('own_choir_request_id', $song->id)->get() as $member) {
            self::removeMember($member);
        }

        OwnChoirInvite::query()->where('own_choir_request_id', $song->id)->delete();
    }

    /** A singer's part in every song they joined (their voice deleted, everything forgotten, the account gone). */
    public static function leaveAll(User $user): void
    {
        foreach (OwnChoirMember::query()->where('user_id', $user->id)->get() as $member) {
            self::removeMember($member);
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

    /** The owner has the part-minutes for a friend's parts: paid Plus by its minutes; the free song and Plus not paid for include them. */
    public static function ownerCanPay(User $owner, float $minutes): bool
    {
        $quota = OwnChoir::quota($owner);

        return $quota['unit'] !== 'minutes' || ($quota['minutesLeft'] ?? 0.0) >= $minutes;
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
        if (! self::on($viewer) || $song->status !== OwnChoirStatus::Ready) {
            return [];
        }

        $notes = self::partNotes($song);
        $owner = $song->user_id === $viewer->id;
        $ownerRange = $owner ? self::ownerRange($song, $notes) : null;
        $members = OwnChoirMember::query()->with(['user', 'job.parts'])->where('own_choir_request_id', $song->id)->get();
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
        if (! self::on($viewer) || $song->user_id !== $viewer->id) {
            return [];
        }

        return array_values(OwnChoirMember::query()->with(['user', 'job'])->where('own_choir_request_id', $song->id)->orderBy('id')->get()->map(static fn (OwnChoirMember $m): array => self::member($m))->all());
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
            'name' => self::firstName($member->user),
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
        $have = OwnChoirMember::query()->where('own_choir_request_id', $song->id)->count();
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
        if (! self::on($user)) {
            return [];
        }

        return array_values(OwnChoirMember::query()->with(['user', 'job', 'song.templateVersion.template', 'song.user'])->where('user_id', $user->id)->latest('joined_at')->get()
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
        $notes = [];

        foreach ((array) ($song->templateVersion->data['parts'] ?? []) as $part) {
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
    public static function range(User $user, ?string $sangAlong, array $notes): ?array
    {
        $progress = (array) (UserLearning::query()->where('user_id', $user->id)->first()->progress ?? []);
        $range = $progress['range'] ?? null;

        if (($progress['rangeSource'] ?? null) === 'measured' && is_array($range) && is_numeric($range['low'] ?? null) && is_numeric($range['high'] ?? null)) {
            return [(int) $range['low'], (int) $range['high']];
        }

        if ($sangAlong !== null && isset($notes[$sangAlong])) {
            return ChoirAssign::fromPart($notes[$sangAlong]);
        }

        // Their saved voice: the part they sang along to when they made it.
        $voice = OwnChoir::voice($user);
        $origin = $voice === null ? null : OwnChoirRequest::query()->with('templateVersion')->where('own_choir_voice_id', $voice->id)->whereNotNull('part_key')->oldest('id')->first();
        $originNotes = $origin === null ? [] : self::partNotes($origin);

        return $origin !== null && isset($originNotes[(string) $origin->part_key]) ? ChoirAssign::fromPart($originNotes[(string) $origin->part_key]) : null;
    }

    /**
     * The parts a person's voice would sing on an invite: some of its named parts, never another (the UX review), those
     * it suits better than the owner's, among those no other friend has; null while their range can't be told.
     *
     * @return list<string>|null
     */
    public static function assignFor(OwnChoirInvite $invite, User $user, ?string $sangAlong): ?array
    {
        $ranges = self::ranges($invite, $user, $sangAlong);

        return $ranges === null ? null : self::assignWith($invite, $user, $ranges);
    }

    /**
     * The friend's range and the owner's, read once (accept checks them before its lock and again under it).
     *
     * @return array{friend: array{0: int, 1: int}, owner: array{0: int, 1: int}}|null
     */
    private static function ranges(OwnChoirInvite $invite, User $user, ?string $sangAlong): ?array
    {
        $notes = self::partNotes($invite->song);
        $friend = self::range($user, $sangAlong, $notes);
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

        $taken = OwnChoirMember::query()->where('own_choir_request_id', $song->id)->where('user_id', '!=', $user->id)->pluck('parts')->flatten()->map(static fn (mixed $p): string => (string) $p)->all();
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
        return self::range($song->user, $song->part_key, $notes);
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
