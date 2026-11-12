<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Mail\Account\VerifyEmailAddress;
use App\Models\EmailSuppression;
use App\Models\EmailToken;
use App\Models\User;
use App\Support\Accounts\AddressLimits;
use App\Support\Accounts\EmailTokens;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Send the verification link again: only to an unverified account with a password, within the
 * address's caps and the sender's network's, and silently otherwise (the same answer either way).
 */
final class ResendVerification
{
    public function __construct(private readonly EmailTokens $tokens, private readonly AddressLimits $limits) {}

    /** @param  list<string>  $doneLessons  the free course's lessons the device finished signed out (the mail's next lesson) */
    public function handle(string $email, ?string $ip = null, ?string $browserHash = null, array $doneLessons = []): void
    {
        $email = strtolower(trim($email));
        $user = User::query()->where('email', $email)->whereNull('email_verified_at')->whereNotNull('password')->first();

        if ($user === null || EmailSuppression::isSuppressed($email) || ! $this->limits->allows('verification-email', $email, $ip)) {
            return;
        }

        // To the account's own address, never the one typed (docs/security-review.md).
        // Bound like the sign-up's (SignUpBrowser): to the secret the asker holds, else to one nobody
        // holds, so a link asked for without it starts the account fresh. Never unbound.
        $bound = $browserHash ?? hash('sha256', Str::random(48));
        Mail::to((string) $user->email)->locale($user->preferredLocale())->send(new VerifyEmailAddress($this->tokens->issue($user, EmailToken::VERIFY, (string) $user->email, 24, browserHash: $bound), $user->name, $doneLessons));
    }
}
