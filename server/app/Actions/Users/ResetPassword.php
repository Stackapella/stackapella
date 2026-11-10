<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Enums\SecurityEventType;
use App\Exceptions\Refusal;
use App\Models\EmailToken;
use App\Models\User;
use App\Support\Accounts\PasswordFailures;
use App\Support\Accounts\Sessions;
use App\Support\Trust\SecurityLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

/**
 * A new password from the reset link: every other session ends, and following the link proves
 * the address, so it's verified too. The caller signs the person in. An account the address had
 * never proven may be a stranger's sign-up with it: what it said about itself goes, as when a
 * sign-in link opens it (SignInByLink), and the owner is asked their name and age again.
 */
final class ResetPassword
{
    /** A bcrypt hash of nothing anyone knows, checked to spend the same time when there's no token. */
    private const NOTHING = '$2y$12$ZjiPVEDCn1KVtDeWxvt1huorO20LGrk.Nwk/2J1WrRdsYwuVy6qNu';

    public function __construct(private readonly Sessions $sessions, private readonly PasswordFailures $failures, private readonly SecurityLog $securityLog) {}

    public function handle(string $email, string $token, string $password, ?string $ip): User
    {
        $user = User::query()->where('email', strtolower(trim($email)))->first();
        $broker = Password::broker();

        // The same work either way (docs/agents/backend.md, "No enumeration"): with no account, or no
        // reset waiting, a bcrypt check against nothing, as checking a real token costs.
        $waiting = $user !== null && DB::table((string) config('auth.passwords.users.table'))->where('email', $user->email)->exists();

        if (! $waiting) {
            Hash::check($token, self::NOTHING);
        }

        if ($user === null || ! $waiting || ! $broker->tokenExists($user, $token)) {
            throw new Refusal(410, 'link_expired');
        }

        if ($user->isSuspended()) {
            throw new Refusal(403, 'suspended');
        }

        $broker->deleteToken($user);

        if ($user->email_verified_at === null) {
            EmailToken::query()->where('user_id', $user->id)->where('purpose', EmailToken::VERIFY)->delete();
            $user->forceFill([
                'name' => '',
                'pending_invite_hash' => null,
                'birth_month' => null,
                'birth_year' => null,
                'age_confirmed_at' => null,
                'adult_confirmed_at' => null,
            ]);
        }

        $user->forceFill(['password' => $password, 'email_verified_at' => $user->email_verified_at ?? now()])->save();
        $this->sessions->endAll($user);
        $this->failures->clearEverywhere((string) $user->email);
        $this->securityLog->record(SecurityEventType::PasswordReset, $user, $ip);

        return $user;
    }
}
