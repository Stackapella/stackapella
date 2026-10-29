<?php

declare(strict_types=1);

namespace App\Actions\Beta;

use App\Exceptions\Refusal;
use App\Models\AuditLog;
use App\Models\BetaSinger;
use App\Models\User;
use App\Support\Trust\TrustLevel;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * beta.invite.accept: an invite attaches its singer record (the role, the agreement, the parts and
 * takes) to the account that accepts it, once. Only an account whose address is proven can, so an
 * unverified sign-up made with the singer's address can't claim them.
 */
final class AcceptInvite
{
    public function __construct(private readonly TrustLevel $trust) {}

    public function handle(User $user, string $token): BetaSinger
    {
        return DB::transaction(function () use ($user, $token): BetaSinger {
            $singer = BetaSinger::query()->where('token_hash', hash('sha256', $token))->lockForUpdate()->first();

            if ($singer === null) {
                throw new Refusal(404, 'invite_not_found');
            }

            if ($singer->user_id === $user->id) {
                return $singer->setRelation('user', $user);
            }

            if ($singer->user_id !== null) {
                throw new Refusal(409, 'invite_used');
            }

            if (! $singer->isLive()) {
                throw new Refusal(410, 'invite_revoked');
            }

            if ($singer->expires_at !== null && $singer->expires_at->isPast()) {
                throw new Refusal(410, 'invite_expired');
            }

            if ($this->trust->of($user) < TrustLevel::VERIFIED) {
                throw new Refusal(403, 'email_unverified');
            }

            if (BetaSinger::query()->where('user_id', $user->id)->exists()) {
                throw new Refusal(409, 'already_singer');
            }

            try {
                $singer->forceFill(['user_id' => $user->id, 'accepted_at' => now(), 'last_seen_at' => now()])->save();
            } catch (UniqueConstraintViolationException) {
                throw new Refusal(409, 'already_singer');
            }

            $user->forceFill(['pending_invite_hash' => null])->save();
            AuditLog::record('beta.accepted', $singer, $user, after: ['account' => $user->sqid()]);

            return $singer->setRelation('user', $user);
        });
    }

    /**
     * The invite a sign-up carried, accepted once the account can (its address verified, or a
     * Google or Apple sign-in). Quietly not, if it no longer can: the invite page says why.
     */
    public function pending(User $user): void
    {
        $hash = $user->pending_invite_hash;

        if ($hash === null) {
            return;
        }

        $singer = BetaSinger::query()->where('token_hash', $hash)->first();
        $user->forceFill(['pending_invite_hash' => null])->save();

        if ($singer === null || ! $singer->inviteOpen() || $this->trust->of($user) < TrustLevel::VERIFIED || BetaSinger::query()->where('user_id', $user->id)->exists()) {
            return;
        }

        try {
            $claimed = BetaSinger::query()->whereKey($singer->id)->whereNull('user_id')->update(['user_id' => $user->id, 'accepted_at' => now(), 'last_seen_at' => now()]);
        } catch (UniqueConstraintViolationException) {
            // The account became a singer a moment ago, by another invite: this one stays open.
            return;
        }

        if ($claimed === 1) {
            AuditLog::record('beta.accepted', $singer, $user, after: ['account' => $user->sqid()]);
        }
    }

    /**
     * Does this token name an invite that can still be accepted? (A sign-up carrying one gets
     * through paused sign-ups.)
     */
    public static function opens(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        $singer = BetaSinger::query()->where('token_hash', hash('sha256', $token))->first();

        return $singer !== null && $singer->inviteOpen();
    }
}
