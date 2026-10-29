<?php

declare(strict_types=1);

namespace App\Support\Accounts;

use App\Models\AppDevice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ending a person's sessions: "sign out everywhere", and after a new password. Sessions live in
 * the database (SESSION_DRIVER=database), each with its user; a new remember token ends every
 * "remember me" too.
 */
final class Sessions
{
    /**
     * Every session and every app device of theirs ended, but the session or device asking (a
     * password changed keeps the one it was changed on).
     */
    public function endAll(User $user, ?string $except = null, ?int $exceptDevice = null): void
    {
        AppDevice::query()->where('user_id', $user->id)->whereNull('revoked_at')->when($exceptDevice !== null, fn ($query) => $query->whereKeyNot($exceptDevice))
            ->update(['revoked_at' => now(), 'updated_at' => now(), ...AppDevice::NO_PUSH]);

        // The sessions as they're kept, even when this request runs on the array driver (an app's
        // request, an anonymous read): `session.stored_driver` is the one it replaced.
        if ((config('session.stored_driver') ?? config('session.driver')) === 'database') {
            DB::table((string) config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->when($except !== null, fn ($query) => $query->where('id', '!=', $except))
                ->delete();
        }

        $user->forceFill(['remember_token' => Str::random(60)])->save();
    }
}
