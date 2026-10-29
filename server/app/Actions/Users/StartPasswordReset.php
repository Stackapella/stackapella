<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Mail\Account\ResetPassword;
use App\Models\EmailSuppression;
use App\Models\User;
use App\Support\Accounts\AddressLimits;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * "Forgot your password?": a reset link to an address with an account, within the address's caps,
 * and silently nothing otherwise (the same answer either way). Uses Laravel's password broker for
 * the token (hashed, 60 minutes).
 */
final class StartPasswordReset
{
    public function __construct(private readonly AddressLimits $limits) {}

    public function handle(string $email): void
    {
        $email = strtolower(trim($email));
        $user = User::query()->where('email', $email)->whereNull('suspended_at')->first();

        if ($user === null || EmailSuppression::isSuppressed($email) || ! $this->limits->allows('password-reset', $email)) {
            // The token below costs a bcrypt: the same work here, so the time doesn't tell.
            Hash::make(Str::random(40));

            return;
        }

        // To the account's own address, never the one typed (docs/security-review.md).
        Mail::to((string) $user->email)->send(new ResetPassword(Password::broker()->createToken($user), (string) $user->email));
    }
}
