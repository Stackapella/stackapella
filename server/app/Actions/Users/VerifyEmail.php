<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\EmailToken;
use App\Models\User;
use App\Support\Accounts\EmailTokens;
use App\Support\Accounts\Sessions;

/**
 * The verification link: the address is confirmed (trust level 1), and the caller signs them in.
 */
final class VerifyEmail
{
    public function __construct(private readonly EmailTokens $tokens, private readonly Sessions $sessions) {}

    /**
     * The address to confirm before this link signs in (Hillel: "Yes, add the confirm screen"):
     * when another account is signed in here, or it's opened outside the browser or app that signed
     * up; null when it can go straight on.
     */
    public function confirmFor(string $token, ?User $signedIn, ?string $browserHash): ?string
    {
        $row = $this->tokens->peek($token, EmailToken::VERIFY);

        if ($row === null) {
            return null;
        }

        $otherAccount = $signedIn !== null && $signedIn->id !== $row->user_id;
        $elsewhere = $row->browser_hash !== null && ($browserHash === null || ! hash_equals($row->browser_hash, $browserHash));

        return $otherAccount || $elsewhere ? $row->email : null;
    }

    /**
     * `$browserHash`: the hash of the secret this request brought back (SignUpBrowser). A link opened
     * outside the browser or app that signed up proves the inbox but not the sign-up: someone else may
     * have signed up with this address, so its password and what it said go, and the owner starts
     * fresh (as a sign-in link does). Links from before the binding (no hash) verify as they did.
     */
    public function handle(string $token, ?string $browserHash = null): User
    {
        $row = $this->tokens->redeem($token, EmailToken::VERIFY);
        $user = $row->user;

        if ($user->email !== $row->email || $user->email_verified_at !== null) {
            return $user;
        }

        $bound = $row->browser_hash === null || ($browserHash !== null && hash_equals($row->browser_hash, $browserHash));

        if ($bound) {
            $user->forceFill(['email_verified_at' => now()])->save();

            return $user;
        }

        EmailToken::query()->where('user_id', $user->id)->where('purpose', EmailToken::VERIFY)->delete();
        $user->forceFill([
            'email_verified_at' => now(),
            'password' => null,
            'name' => '',
            // A beta invite carried is a secret its holder had: it stays, for when they finish.
            'birth_month' => null,
            'birth_year' => null,
            'age_confirmed_at' => null,
            'adult_confirmed_at' => null,
        ])->save();
        $this->sessions->endAll($user);

        return $user;
    }
}
