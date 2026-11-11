<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\Actions\Support\SupportInbox;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * "Send us your song" (docs/choir-tracks-plan.md, stage 1, free): a director asks for learning tracks of one piece for
 * their choir group, kept in the support inbox as a `tracks` message from their account (no other address is asked
 * for or kept), with the group, the piece, the score's files sent first (TrackScores), and their word on its rights:
 * the exact sentence they confirmed, the written confirmation the plan asks for and the record Give this song keeps.
 * With no score, Hillel's reply asks for it.
 */
final class RequestTracks
{
    /** The two kinds of piece stage 1 takes: public domain, or the choir's own arrangement. */
    public const RIGHTS = ['public-domain', 'own-arrangement'];

    public function __construct(private readonly ManageGroups $groups, private readonly SupportInbox $inbox, private readonly TrackScores $scores) {}

    /**
     * @param  array{piece: string, composer?: string|null, voicing?: string|null, rights: string, note?: string|null, scoreIds?: list<string>|null}  $data
     */
    public function handle(User $user, string $groupId, array $data, string $ip): SupportMessage
    {
        $group = $this->groups->directed($user, $groupId);
        $text = static fn (?string $value): ?string => $value !== null && trim($value) !== '' ? trim($value) : null;
        $piece = trim($data['piece']);
        $note = $text($data['note'] ?? null);

        return DB::transaction(function () use ($user, $group, $data, $text, $piece, $note, $ip): SupportMessage {
            $message = $this->inbox->receive($user, [
                'category' => SupportMessage::TRACKS,
                'message' => $note ?? __('support.tracks.body', ['piece' => $piece]),
                'context' => ['tracks' => [
                    'groupId' => $group->sqid(),
                    'group' => $group->name,
                    'piece' => $piece,
                    'composer' => $text($data['composer'] ?? null),
                    'voicing' => $text($data['voicing'] ?? null),
                    'rights' => $data['rights'],
                    'rightsConfirmedAt' => now()->utc()->toIso8601ZuluString(),
                    'rightsStatement' => (string) __('support.tracks.rights.'.$data['rights']),
                ]],
            ], $ip);

            $this->scores->attach($user, $group, array_values($data['scoreIds'] ?? []), $message);

            return $message;
        });
    }
}
