<?php

declare(strict_types=1);

namespace App\Actions\Beta;

use App\Exceptions\Refusal;
use App\Models\BetaSinger;
use App\Support\Beta\TakeBar;

/**
 * beta.invite.show: who an invite link is for, and whether it can still be accepted, for the page
 * the link opens. A token that was never ours is 404; nothing else about any invite is told.
 */
final class OpenInviteLink
{
    /**
     * @return array{name: string, status: 'open'|'accepted'|'expired'|'revoked', bar: array{pitchPercent: int, timingPercent: int}}
     */
    public function handle(string $token): array
    {
        $singer = BetaSinger::query()->where('token_hash', hash('sha256', $token))->first();

        if ($singer === null) {
            throw new Refusal(404, 'invite_not_found');
        }

        $status = match (true) {
            ! $singer->isLive() => 'revoked',
            $singer->user_id !== null => 'accepted',
            $singer->expires_at !== null && $singer->expires_at->isPast() => 'expired',
            default => 'open',
        };

        return ['name' => $singer->name, 'status' => $status, 'bar' => TakeBar::current()];
    }
}
