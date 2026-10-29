<?php

declare(strict_types=1);

namespace App\Support\Accounts;

use App\Enums\SecurityEventType;
use App\Exceptions\Refusal;
use App\Mail\Account\NewSignIn;
use App\Models\AppDevice;
use App\Models\User;
use App\Models\UserDevice;
use App\Support\Ids;
use App\Support\Trust\Limits;
use App\Support\Trust\SecurityLog;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use JsonException;

/**
 * The native apps' tokens (the mobile agent's token-auth plan; contract/api.md, "The apps: device
 * tokens"): each sign-in in an app is a device with an access token (an hour) and a refresh
 * token (90 days from its last use, rotated on every refresh). Only their SHA-256 hashes are
 * kept. For a minute, a retired refresh token gets the same pair its refresh gave (a reply lost on
 * a bad connection, or two refreshes at once), never a new one; after that it's taken as stolen,
 * and the device is signed out.
 */
final class AppTokens
{
    public const ACCESS_MINUTES = 60;

    public const REFRESH_DAYS = 90;

    /** How long a just-retired refresh token still works. */
    public const GRACE_SECONDS = 60;

    /** The pair a refresh gave, kept encrypted for the minute, by the retired token's hash. */
    private const GIVEN = 'app-refresh-given:';

    public function __construct(private readonly SecurityLog $securityLog, private readonly Limits $limits) {}

    /**
     * A sign-in on a device: a new device, or (with `replace`, the caller's own device) the same
     * one with a new pair and a new sign-in time.
     *
     * @param  array{name: string, platform: string, model?: string|null, appVersion?: string|null, replace?: string|null}  $device
     * @return array{accessToken: string, accessExpiresAt: string, refreshToken: string, device: AppDevice}
     */
    public function issue(User $user, array $device, string $method, ?string $ip, bool $keepSignedInAt = false): array
    {
        if ($user->isSuspended()) {
            throw new Refusal(403, 'suspended');
        }

        $row = ($device['replace'] ?? null) !== null
            ? AppDevice::query()->whereKey(Ids::decode((string) $device['replace']) ?? 0)->where('user_id', $user->id)->whereNull('revoked_at')->first()
            : null;
        $row ??= new AppDevice;
        $isNew = ! $row->exists;

        [$access, $refresh] = [self::make('sat_', 40), self::make('srt_', 48)];
        $row->forceFill([
            'user_id' => $user->id,
            'name' => mb_substr($device['name'], 0, 80),
            'platform' => $device['platform'],
            'model' => isset($device['model']) ? mb_substr((string) $device['model'], 0, 80) : null,
            'app_version' => isset($device['appVersion']) ? mb_substr((string) $device['appVersion'], 0, 40) : null,
            'access_hash' => hash('sha256', $access),
            'access_expires_at' => now()->addMinutes(self::ACCESS_MINUTES),
            'refresh_hash' => hash('sha256', $refresh),
            'previous_refresh_hash' => null,
            'refreshed_at' => null,
            'refresh_expires_at' => now()->addDays(self::REFRESH_DAYS),
            'method' => $method,
            // Linking Google or Apple re-issues the pair, but isn't a new sign-in.
            'signed_in_at' => $keepSignedInAt && $row->exists ? $row->signed_in_at : now(),
            'last_used_at' => now(),
        ])->save();
        $this->securityLog->record(SecurityEventType::SignIn, $user, $ip, ['method' => $method, 'app' => $row->platform]);

        if ($isNew) {
            self::tellNewDevice($user, $row);
        }

        return ['accessToken' => $access, 'accessExpiresAt' => $row->access_expires_at->utc()->toIso8601ZuluString(), 'refreshToken' => $refresh, 'device' => $row];
    }

    /**
     * auth.app.refresh: a new pair for the refresh token sent, under the device's lock.
     *
     * @return array{accessToken: string, accessExpiresAt: string, refreshToken: string, device: AppDevice}
     */
    public function refresh(string $token, ?string $ip): array
    {
        $hash = hash('sha256', $token);

        $issued = DB::transaction(function () use ($hash, $ip): array|AppDevice|int {
            $device = AppDevice::query()->with('user')->where('refresh_hash', $hash)->lockForUpdate()->first();
            $retired = false;

            if ($device === null) {
                $device = AppDevice::query()->with('user')->where('previous_refresh_hash', $hash)->lockForUpdate()->first();
                $retired = $device !== null;
            }

            if ($device === null || $device->revoked_at !== null || $device->refresh_expires_at->isPast() || $device->user->isSuspended()) {
                throw new Refusal(401, 'refresh_invalid');
            }

            // Counted per account once the token is known (a whole carrier's phones can share an IP).
            // Refused after the transaction, so the limit's security event is kept.
            if (($wait = $this->limits->attempt('app-refresh', $device->user, (string) $ip)) !== null) {
                return $wait;
            }

            if ($retired) {
                $given = self::given($hash);

                // Within the minute: the same pair again, so a replay can never take the device over.
                if ($given !== null && $device->refreshed_at !== null && $device->refreshed_at->gte(now()->subSeconds(self::GRACE_SECONDS))) {
                    return [...$given, 'device' => $device];
                }

                // After it: someone else has it. The device is signed out.
                $device->forceFill(['revoked_at' => now()])->save();

                return $device;
            }

            [$access, $refresh] = [self::make('sat_', 40), self::make('srt_', 48)];
            $device->forceFill([
                'access_hash' => hash('sha256', $access),
                'access_expires_at' => now()->addMinutes(self::ACCESS_MINUTES),
                'previous_refresh_hash' => $device->refresh_hash,
                'refresh_hash' => hash('sha256', $refresh),
                'refreshed_at' => now(),
                'refresh_expires_at' => now()->addDays(self::REFRESH_DAYS),
                'last_used_at' => now(),
            ])->save();
            $pair = ['accessToken' => $access, 'accessExpiresAt' => $device->access_expires_at->utc()->toIso8601ZuluString(), 'refreshToken' => $refresh];
            Cache::put(self::GIVEN.$hash, Crypt::encryptString((string) json_encode($pair)), self::GRACE_SECONDS);

            return [...$pair, 'device' => $device];
        });

        if (is_int($issued)) {
            throw new Refusal(429, 'rate_limited', ['retryAfter' => $issued], ['Retry-After' => (string) $issued]);
        }

        // Refused once the sign-out is committed (a refusal inside the transaction would undo it).
        if ($issued instanceof AppDevice) {
            $this->securityLog->record(SecurityEventType::SignIn, $issued->user, $ip, ['method' => 'refresh', 'refused' => 'token_reused']);

            throw new Refusal(401, 'token_reused');
        }

        return $issued;
    }

    /**
     * The signed-in device an access token is from, even past its hour, or null.
     */
    public static function owner(string $token): ?AppDevice
    {
        $device = str_starts_with($token, 'sat_') ? AppDevice::query()->where('access_hash', hash('sha256', $token))->whereNull('revoked_at')->first() : null;

        return $device;
    }

    /**
     * The device a bearer access token belongs to: none (401 unauthenticated) for an unknown one
     * or a signed-out device, `token_expired` for one past its hour.
     */
    public static function device(string $token): AppDevice
    {
        $device = str_starts_with($token, 'sat_') ? AppDevice::query()->with('user')->where('access_hash', hash('sha256', $token))->first() : null;

        if ($device === null || $device->revoked_at !== null || $device->user->isSuspended()) {
            throw new Refusal(401, 'unauthenticated');
        }

        if ($device->access_expires_at->isPast()) {
            throw new Refusal(401, 'token_expired');
        }

        // Its last use, at most every few minutes (the list's "last used").
        if ($device->last_used_at === null || $device->last_used_at->lt(now()->subMinutes(5))) {
            AppDevice::query()->whereKey($device->id)->update(['last_used_at' => now()]);
        }

        return $device;
    }

    /**
     * Every app device of theirs signed out ("Sign out everywhere", account deletion).
     */
    public static function revokeAll(User $user): void
    {
        AppDevice::query()->where('user_id', $user->id)->whereNull('revoked_at')->update(['revoked_at' => now(), 'updated_at' => now(), ...AppDevice::NO_PUSH]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function view(AppDevice $device, ?AppDevice $current): array
    {
        return [
            'id' => $device->sqid(),
            'name' => $device->name,
            'platform' => $device->platform,
            'model' => $device->model,
            'appVersion' => $device->app_version,
            'signedInAt' => $device->signed_in_at->utc()->toIso8601ZuluString(),
            'lastUsedAt' => $device->last_used_at?->utc()->toIso8601ZuluString(),
            'current' => $current !== null && $current->id === $device->id,
            // Whether the device has a push token (contract/api.md, account.devices.push.update).
            'push' => $device->push_token_hash !== null,
        ];
    }

    /**
     * The pair a refresh gave for a now-retired token, while its minute lasts.
     *
     * @return array{accessToken: string, accessExpiresAt: string, refreshToken: string}|null
     */
    private static function given(string $hash): ?array
    {
        $kept = Cache::get(self::GIVEN.$hash);

        if (! is_string($kept)) {
            return null;
        }

        try {
            /** @var array{accessToken: string, accessExpiresAt: string, refreshToken: string} $pair */
            $pair = json_decode(Crypt::decryptString($kept), true, 4, JSON_THROW_ON_ERROR);

            return $pair;
        } catch (DecryptException|JsonException) {
            return null;
        }
    }

    /**
     * A new device in the app, as a new browser on the web: an email, except for the account's
     * very first sign-in, and only to a verified address. The device is named by its platform,
     * never by the name sent (whoever signs in chooses that).
     */
    private static function tellNewDevice(User $user, AppDevice $device): void
    {
        $firstEver = ! UserDevice::query()->where('user_id', $user->id)->exists()
            && ! AppDevice::query()->where('user_id', $user->id)->whereKeyNot($device->id)->exists();

        if (! $firstEver && $user->email !== null && $user->email_verified_at !== null) {
            Mail::to($user->email)->send(new NewSignIn(__('account.mail.new_sign_in.platforms.'.$device->platform), true));
        }
    }

    private static function make(string $prefix, int $length): string
    {
        return $prefix.Str::random($length);
    }
}
