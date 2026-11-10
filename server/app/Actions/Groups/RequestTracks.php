<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\Actions\Support\SupportInbox;
use App\Models\SupportMessage;
use App\Models\User;

/**
 * A director asks for learning tracks of one piece for their choir group (docs/choir-tracks-plan.md, stage 1): kept
 * in the support inbox as a `tracks` message, from their account (no other address is asked for or kept), with the
 * group, the piece and their word on its rights, which is the written confirmation the plan asks for. The score
 * comes by email in answer to Hillel's reply, so no score is ever stored here.
 */
final class RequestTracks
{
    /** The two kinds of piece stage 1 takes: public domain, or the choir's own arrangement. */
    public const RIGHTS = ['public-domain', 'own-arrangement'];

    public function __construct(private readonly ManageGroups $groups, private readonly SupportInbox $inbox) {}

    /**
     * @param  array{piece: string, composer?: string|null, voicing?: string|null, rights: string, note?: string|null}  $data
     */
    public function handle(User $user, string $groupId, array $data, string $ip): SupportMessage
    {
        $group = $this->groups->directed($user, $groupId);
        $text = static fn (?string $value): ?string => $value !== null && trim($value) !== '' ? trim($value) : null;
        $piece = trim($data['piece']);
        $note = $text($data['note'] ?? null);

        return $this->inbox->receive($user, [
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
            ]],
        ], $ip);
    }
}
