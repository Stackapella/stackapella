<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Exceptions\Refusal;
use App\Models\User;
use App\Services\Auth\ProviderTokens;
use App\Support\Accounts\Sessions;

/**
 * Sign-in methods from Settings (docs/backend-plan.md, B): a password set or changed, and Google,
 * Apple or the password removed. The last one can't be removed, and a new password signs out
 * every other session.
 */
final class ManageSignInMethods
{
    public function __construct(private readonly Sessions $sessions, private readonly ProviderTokens $providers) {}

    public function setPassword(User $user, string $password, ?string $keepSession, ?int $keepDevice = null): User
    {
        $user->forceFill(['password' => $password])->save();
        $this->sessions->endAll($user, except: $keepSession, exceptDevice: $keepDevice);

        return $user;
    }

    /**
     * @param  'google'|'apple'|'event-schedule'|'password'  $method
     */
    public function remove(User $user, string $method): User
    {
        $methods = ['google' => $user->google_id !== null, 'apple' => $user->apple_id !== null, 'event-schedule' => $user->event_schedule_id !== null, 'password' => $user->password !== null];

        if (! ($methods[$method] ?? false)) {
            return $user;
        }

        if (count(array_filter($methods)) <= 1) {
            throw new Refusal(409, 'last_method');
        }

        $user->forceFill([$method === 'password' ? 'password' : str_replace('-', '_', $method).'_id' => null])->save();

        // Apple no longer a way in: what it gave us is revoked with it.
        if ($method === 'apple') {
            $this->providers->revokeAppleFor($user);
        }

        return $user;
    }
}
