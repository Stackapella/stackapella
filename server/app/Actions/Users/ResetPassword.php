<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Enums\SecurityEventType;
use App\Exceptions\Refusal;
use App\Models\User;
use App\Support\Accounts\PasswordFailures;
use App\Support\Accounts\Sessions;
use App\Support\Trust\SecurityLog;
use Illuminate\Support\Facades\Password;

/**
 * A new password from the reset link: every other session ends, and following the link proves
 * the address, so it's verified too. The caller signs the person in.
 */
final class ResetPassword
{
    public function __construct(private readonly Sessions $sessions, private readonly PasswordFailures $failures, private readonly SecurityLog $securityLog) {}

    public function handle(string $email, string $token, string $password, ?string $ip): User
    {
        $user = User::query()->where('email', strtolower(trim($email)))->first();
        $broker = Password::broker();

        if ($user === null || ! $broker->tokenExists($user, $token)) {
            throw new Refusal(410, 'link_expired');
        }

        if ($user->isSuspended()) {
            throw new Refusal(403, 'suspended');
        }

        $broker->deleteToken($user);
        $user->forceFill(['password' => $password, 'email_verified_at' => $user->email_verified_at ?? now()])->save();
        $this->sessions->endAll($user);
        $this->failures->clearEverywhere((string) $user->email);
        $this->securityLog->record(SecurityEventType::PasswordReset, $user, $ip);

        return $user;
    }
}
