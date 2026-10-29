<?php

declare(strict_types=1);

namespace App\Support\Accounts;

use App\Exceptions\Refusal;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Changing the email or password, or deleting the account, needs the current password or a
 * sign-in in the last 10 minutes (403 recent_sign_in_required). A wrong password is a 422.
 */
final class Reauthentication
{
    public function __construct(private readonly PasswordFailures $failures) {}

    public function ensure(Request $request, User $user, ?string $password, string $field = 'password'): void
    {
        if ($password !== null && $password !== '') {
            // The same count as signing in, so a borrowed session can't guess the password here.
            $counted = $user->email ?? 'user:'.$user->id;
            $this->failures->ensureAllowed($counted, $request->ip());

            if ($user->password === null || ! Hash::check($password, $user->password)) {
                $this->failures->failed($counted, $request->ip());

                throw ValidationException::withMessages([$field => __('validation.current_password')]);
            }

            return;
        }

        if (! SignInSession::isRecent($request)) {
            throw new Refusal(403, 'recent_sign_in_required');
        }
    }
}
