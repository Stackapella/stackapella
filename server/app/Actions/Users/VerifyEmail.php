<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\EmailToken;
use App\Models\User;
use App\Support\Accounts\EmailTokens;

/**
 * The verification link: the address is confirmed (trust level 1), and the caller signs them in.
 */
final class VerifyEmail
{
    public function __construct(private readonly EmailTokens $tokens) {}

    public function handle(string $token): User
    {
        $row = $this->tokens->redeem($token, EmailToken::VERIFY);
        $user = $row->user;

        if ($user->email === $row->email && $user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return $user;
    }
}
