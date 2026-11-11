<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\Models\AuditLog;
use App\Models\Group;
use App\Models\GroupSong;
use App\Models\SupportMessage;
use App\Models\Template;
use App\Models\TrackScore;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Where a director's learning tracks requests have got to (RequestTracks keeps them as `tracks` support messages):
 * `received`, `delivered` once Give this song is run from the request (the song shared into the group, the message
 * closed, and the group page saying it's ready: the director's in-app note, never an email), or `closed` when Hillel
 * closed it without a song.
 */
final class TrackRequests
{
    public const RECEIVED = 'received';

    public const DELIVERED = 'delivered';

    public const CLOSED = 'closed';

    public function __construct(private readonly ManageGroups $groups) {}

    /**
     * The director's requests for one of their groups, newest first.
     *
     * @return list<array{id: string, piece: string, status: string, scores: int, song: array{id: string, title: string}|null, createdAt: string|null, deliveredAt: string|null}>
     */
    public function forGroup(User $user, string $groupId): array
    {
        $group = $this->groups->directed($user, $groupId);
        $messages = SupportMessage::query()->where('user_id', $user->id)->where('category', SupportMessage::TRACKS)->where('context->tracks->groupId', $group->sqid())->latest('id')->limit(100)->get();
        $counts = TrackScore::query()->whereIn('support_message_id', $messages->modelKeys())->where('status', TrackScore::READY)->groupBy('support_message_id')->selectRaw('support_message_id, count(*) as n')->pluck('n', 'support_message_id');

        return array_values($messages->map(static fn (SupportMessage $message): array => [
            'id' => $message->sqid(),
            'piece' => (string) ($message->context['tracks']['piece'] ?? ''),
            'status' => self::status($message),
            'scores' => (int) ($counts[$message->id] ?? 0),
            'song' => self::song($message),
            'createdAt' => $message->created_at?->utc()->toIso8601ZuluString(),
            'deliveredAt' => self::text($message, 'deliveredAt'),
        ])->all());
    }

    public static function status(SupportMessage $message): string
    {
        return match (true) {
            self::text($message, 'deliveredAt') !== null => self::DELIVERED,
            $message->status === SupportMessage::CLOSED => self::CLOSED,
            default => self::RECEIVED,
        };
    }

    /**
     * @return array{id: string, title: string}|null
     */
    public static function song(SupportMessage $message): ?array
    {
        $song = $message->context['tracks']['song'] ?? null;

        return is_array($song) && is_string($song['id'] ?? null) && is_string($song['title'] ?? null) ? ['id' => $song['id'], 'title' => $song['title']] : null;
    }

    /**
     * Give this song's rights record, from the request: who confirmed, the exact sentence, when, and where it's kept.
     *
     * @return array{rights: array{confirmedBy: string, confirmedAt: string, wording: string, source: string}}|null
     */
    public static function give(SupportMessage $message): ?array
    {
        $wording = self::text($message, 'rightsStatement');
        $at = self::text($message, 'rightsConfirmedAt');

        if ($wording === null || $at === null) {
            return null;
        }

        return ['rights' => [
            'confirmedBy' => (string) __('support.tracks.confirmed_by', ['name' => $message->user->name ?? '', 'email' => (string) $message->email, 'group' => (string) self::text($message, 'group')]),
            'confirmedAt' => $at,
            'wording' => $wording,
            'source' => (string) __('support.tracks.source', ['id' => $message->sqid(), 'date' => substr($at, 0, 10)]),
        ]];
    }

    /**
     * The request Give this song is about to deliver: this account's own, and not yet delivered.
     */
    public function pending(User $person, string $messageId): SupportMessage
    {
        $message = SupportMessage::query()->whereSqid($messageId)->where('category', SupportMessage::TRACKS)->where('user_id', $person->id)->first();

        if ($message === null || self::status($message) === self::DELIVERED) {
            throw ValidationException::withMessages(['trackRequest' => __('validation.track_request')]);
        }

        return $message;
    }

    /**
     * Give this song ran from the request: the song goes into the request's group (while the person still directs
     * it), the request says it's delivered with the song, and the message is closed. Audited.
     */
    public function deliver(User $person, SupportMessage $message, Template $song, User $admin, ?string $ip = null): void
    {
        DB::transaction(function () use ($person, $message, $song, $admin, $ip): void {
            $locked = SupportMessage::query()->whereKey($message->id)->lockForUpdate()->firstOrFail();

            if (self::status($locked) === self::DELIVERED) {
                throw ValidationException::withMessages(['trackRequest' => __('validation.track_request')]);
            }

            $groupId = self::text($locked, 'groupId');
            $group = $groupId !== null ? Group::query()->whereSqid($groupId)->where('director_id', $person->id)->first() : null;

            if ($group !== null) {
                GroupSong::query()->firstOrCreate(['group_id' => $group->id, 'template_id' => $song->id]);
            }

            $context = (array) $locked->context;
            $context['tracks'] = [...(array) ($context['tracks'] ?? []), 'song' => ['id' => $song->slug, 'title' => $song->title], 'deliveredAt' => now()->utc()->toIso8601ZuluString()];
            $locked->forceFill(['context' => $context, 'status' => SupportMessage::CLOSED, 'closed_at' => now()])->save();
            AuditLog::record('support.tracks_delivered', $locked, $admin, after: ['song' => $song->slug, 'group' => $groupId, 'shared' => $group !== null], ip: $ip);
        });
    }

    private static function text(SupportMessage $message, string $key): ?string
    {
        $value = $message->context['tracks'][$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
