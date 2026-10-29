<?php

declare(strict_types=1);

namespace App\Support\Accounts;

use App\Enums\SecurityEventType;
use App\Exceptions\Refusal;
use App\Http\Middleware\AuthenticateAppDevice;
use App\Mail\Account\NewSignIn;
use App\Models\AppDevice;
use App\Models\User;
use App\Models\UserDevice;
use App\Support\Trust\Country;
use App\Support\Trust\SecurityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Starting a signed-in session the same way for every method (iaw's): a clean session, then the
 * sign-in, then a new session id. It remembers when, for actions that need a recent sign-in, and
 * which browser (a random cookie, kept as an HMAC), so a sign-in from a new one can be mentioned
 * to the person by email.
 */
final class SignInSession
{
    public const SIGNED_IN_AT = 'auth.signed_in_at';

    public const RECENT_MINUTES = 10;

    private const DEVICE_COOKIE = 'stackapella_device';

    public function __construct(private readonly SecurityLog $securityLog) {}

    public function start(Request $request, User $user, string $method): void
    {
        // Every way in ends here, so a suspended account is refused by all of them.
        if ($user->isSuspended()) {
            throw new Refusal(403, 'suspended');
        }

        $session = $request->session();
        $session->invalidate();
        // Every sign-in is remembered, with no checkbox (Hillel, 2026-10-29: "Keep people logged in
        // forever"); `remember` on auth.signin is still accepted, and ignored.
        Auth::login($user, true);
        $session->regenerate();
        $session->put(self::SIGNED_IN_AT, now()->getTimestamp());

        $this->rememberDevice($request, $user);
        Country::remember($user, $request);
        $this->securityLog->record(SecurityEventType::SignIn, $user, $request->ip(), ['method' => $method]);
    }

    public static function isRecent(Request $request): bool
    {
        // An app counts from when its device signed in (signing in again on it refreshes it).
        $device = $request->attributes->get(AuthenticateAppDevice::DEVICE);

        if ($device instanceof AppDevice) {
            return $device->signed_in_at->gt(now()->subMinutes(self::RECENT_MINUTES));
        }

        $at = $request->session()->get(self::SIGNED_IN_AT);

        return is_int($at) && now()->getTimestamp() - $at <= self::RECENT_MINUTES * 60;
    }

    private function rememberDevice(Request $request, User $user): void
    {
        $cookie = $request->cookies->get(self::DEVICE_COOKIE);
        $id = is_string($cookie) && strlen($cookie) === 40 ? $cookie : Str::random(40);
        $hash = hash_hmac('sha256', 'device|'.$id, (string) config('app.key'));

        Cookie::queue(Cookie::make(self::DEVICE_COOKIE, $id, 60 * 24 * 365 * 2, '/', null, null, true, false, 'lax'));

        $known = UserDevice::query()->where('user_id', $user->id)->where('device_hash', $hash)->first();

        if ($known !== null) {
            $known->forceFill(['last_seen_at' => now()])->save();

            return;
        }

        $firstEver = ! UserDevice::query()->where('user_id', $user->id)->exists();
        $device = new UserDevice(['device_hash' => $hash, 'last_seen_at' => now()]);
        $device->forceFill(['user_id' => $user->id])->save();

        // Not for the very first sign-in (that's the account being made), and only to a verified address.
        if (! $firstEver && $user->email !== null && $user->email_verified_at !== null) {
            Mail::to($user->email)->send(new NewSignIn(self::browserName((string) $request->userAgent())));
        }
    }

    /**
     * A plain name for a browser ("Safari on iPhone"), never the raw user agent.
     */
    public static function browserName(string $agent): string
    {
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'a browser',
        };
        $device = match (true) {
            str_contains($agent, 'iPhone') => 'iPhone',
            str_contains($agent, 'iPad') => 'iPad',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Mac OS') => 'Mac',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Linux') => 'Linux',
            default => null,
        };

        return $device === null ? $browser : "{$browser}, {$device}";
    }
}
