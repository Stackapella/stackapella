<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Enums\SecurityEventType;
use App\Exceptions\Refusal;
use App\Mail\Account\ConfirmNewEmail;
use App\Mail\Account\EmailChanging;
use App\Models\EmailSuppression;
use App\Models\EmailToken;
use App\Models\User;
use App\Support\Accounts\AddressLimits;
use App\Support\Accounts\EmailTokens;
use App\Support\Accounts\Sessions;
use App\Support\Trust\SecurityLog;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * A new email address (docs/trust-and-safety-plan.md, "Email change"): a confirmation link to the
 * new address, and the old one is told, with "This wasn't me", which keeps the old address and
 * signs everyone out. The address changes only when the new one is confirmed.
 */
final class ChangeEmail
{
    public const REVERT = 'email_revert';

    public function __construct(private readonly EmailTokens $tokens, private readonly AddressLimits $limits, private readonly Sessions $sessions, private readonly SecurityLog $securityLog) {}

    public function request(User $user, string $email): void
    {
        $email = strtolower(trim($email));

        if ($email === $user->email) {
            return;
        }

        // An address someone else has looks the same (pending), but gets nothing, and the link
        // couldn't be used anyway. Every send counts toward the address's caps.
        $token = $this->tokens->issue($user, EmailToken::EMAIL_CHANGE, $email, 24);
        $taken = User::query()->where('email', $email)->exists();

        if (! $taken && ! EmailSuppression::isSuppressed($email) && $this->limits->allows('verification-email', $email)) {
            Mail::to($email)->send(new ConfirmNewEmail($token));
        }

        if ($user->email !== null && $user->email_verified_at !== null) {
            Mail::to($user->email)->send(new EmailChanging($email, $this->tokens->issue($user, self::REVERT, $user->email, 24 * 7, onlyNewest: false)));
        }
    }

    public function confirm(string $token, ?string $ip): User
    {
        $row = $this->tokens->redeem($token, EmailToken::EMAIL_CHANGE);
        $user = $row->user;

        if (User::query()->where('email', $row->email)->whereKeyNot($user->id)->exists()) {
            throw new Refusal(410, 'link_expired');
        }

        $before = $user->email;

        try {
            $user->forceFill(['email' => $row->email, 'email_verified_at' => now()])->save();
        } catch (UniqueConstraintViolationException) {
            // Someone else's account took the address a moment ago.
            throw new Refusal(410, 'link_expired');
        }

        $this->securityLog->record(SecurityEventType::EmailChanged, $user, $ip, ['from' => $before !== null ? substr(hash('sha256', $before), 0, 12) : null]);

        return $user;
    }

    /**
     * "This wasn't me", from the old address: it stays (or comes back), every session ends, and
     * a pending change is dropped.
     */
    public function revert(string $token, ?string $ip): User
    {
        // One transaction, so a refusal leaves the link usable.
        $user = DB::transaction(function () use ($token): User {
            $row = $this->tokens->redeem($token, self::REVERT);
            $user = $row->user;

            // The old address back, even from an unverified sign-up made with it meanwhile (which
            // may be whoever took the account, trying to block this undo). A verified owner keeps it.
            $holder = User::query()->where('email', $row->email)->whereKeyNot($user->id)->lockForUpdate()->first();

            if ($holder !== null && $holder->email_verified_at !== null) {
                throw new Refusal(409, 'email_taken');
            }

            $holder?->forceFill(['email' => null])->save();

            // Later changes' undo links go (they may be the intruder's); an earlier one still wins.
            EmailToken::query()->where('user_id', $user->id)->where(fn ($query) => $query->where('purpose', EmailToken::EMAIL_CHANGE)->orWhere(fn ($later) => $later->where('purpose', self::REVERT)->where('id', '>', $row->id)))->delete();
            // Whoever changed the address may know the password: a new one comes by "Forgot your password?".
            $user->forceFill(['email' => $row->email, 'email_verified_at' => now(), 'password' => null])->save();

            return $user;
        });

        $this->sessions->endAll($user);
        $this->securityLog->record(SecurityEventType::EmailChanged, $user, $ip, ['reverted' => true]);

        return $user;
    }
}
