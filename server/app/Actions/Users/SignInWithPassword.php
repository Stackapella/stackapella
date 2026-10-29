<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Enums\SecurityEventType;
use App\Exceptions\Refusal;
use App\Models\User;
use App\Support\Accounts\PasswordFailures;
use App\Support\Trust\SecurityLog;
use Illuminate\Support\Facades\Hash;

/**
 * Check an email and a password. One answer for a wrong address or password (401
 * invalid_credentials); after 10 wrong passwords for one address, it waits 15 minutes whatever the
 * network (429, PasswordFailures). An unverified address is told so only with the right password.
 */
final class SignInWithPassword
{
    public function __construct(private readonly PasswordFailures $failures, private readonly SecurityLog $securityLog) {}

    public function handle(string $email, string $password, ?string $ip): User
    {
        $email = strtolower(trim($email));
        $this->failures->ensureAllowed($email, $ip);
        $user = User::query()->where('email', $email)->first();

        // The same work whether or not the account exists, so timing doesn't tell either.
        static $dummy = null;
        $hash = $user->password ?? ($dummy ??= Hash::make(bin2hex(random_bytes(16))));
        $ok = Hash::check($password, $hash) && $user !== null && $user->password !== null;

        if (! $ok) {
            $this->failures->failed($email, $ip);

            $this->securityLog->record(SecurityEventType::SignInRefused, $user, $ip, ['reason' => 'invalid_credentials']);

            throw new Refusal(401, 'invalid_credentials');
        }

        if ($user->isSuspended()) {
            throw new Refusal(403, 'suspended');
        }

        if ($user->email_verified_at === null) {
            throw new Refusal(403, 'email_unverified');
        }

        $this->failures->clear($email, $ip);

        return $user;
    }
}
