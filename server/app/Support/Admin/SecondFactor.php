<?php

declare(strict_types=1);

namespace App\Support\Admin;

use App\Enums\SecurityEventType;
use App\Models\AdminSecondFactor;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\Admin\AdminSignedIn;
use App\Notifications\Admin\SecondFactorLocked;
use App\Support\Trust\SecurityLog;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * The admin second factor (iaw's): passkeys (laravel/passkeys) first, TOTP with recovery codes as
 * the backup. It can be turned off only in the local environment.
 */
final class SecondFactor
{
    public const SESSION_PASSED_AT = 'admin.second_factor_at';

    /** Whose check SESSION_PASSED_AT records, so a pass never carries over to another account. */
    public const SESSION_USER = 'admin.second_factor_user';

    public function __construct(
        private readonly Google2FA $google2fa,
        private readonly Admins $admins,
        private readonly SecurityLog $securityLog,
    ) {}

    public function required(): bool
    {
        if (app()->environment('local')) {
            return (bool) config('admin.second_factor_required');
        }

        return true;
    }

    public function isEnrolled(User $user): bool
    {
        return $user->hasPasskeysEnabled() || (bool) $user->secondFactor?->hasTotp();
    }

    public function passedThisSession(Session $session, User $user): bool
    {
        return $session->get(self::SESSION_PASSED_AT) !== null && (int) $session->get(self::SESSION_USER) === (int) $user->getKey();
    }

    public function inSudoWindow(Session $session, User $user): bool
    {
        return $this->sudoUntil($session, $user)?->isFuture() ?? false;
    }

    /**
     * When the last check stops counting for sensitive actions, or null when it already has (or
     * there was none).
     */
    public function sudoUntil(Session $session, User $user): ?Carbon
    {
        if (! $this->passedThisSession($session, $user)) {
            return null;
        }

        $until = Carbon::createFromTimestamp((int) $session->get(self::SESSION_PASSED_AT))->addMinutes((int) config('admin.sudo_window'));

        return $until->isFuture() ? $until : null;
    }

    /**
     * Record a successful check, regenerating the session ID (fixation protection).
     */
    public function markPassed(Session $session, User $user, string $method, string $userAgent, ?string $ip = null): void
    {
        $firstThisSession = ! $this->passedThisSession($session, $user);
        $session->migrate(true);
        $session->put([self::SESSION_PASSED_AT => now()->getTimestamp(), self::SESSION_USER => (int) $user->getKey()]);

        if ($firstThisSession) {
            Notification::send($this->admins->all(), new AdminSignedIn($user, $method, $userAgent));
            AuditLog::record('admin.second_factor_passed', $user, $user, after: ['method' => $method], ip: $ip);
            $this->securityLog->record(SecurityEventType::AdminSignIn, $user, $ip, ['method' => $method]);
        }
    }

    public function newTotpSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    public function totpUri(User $user, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl('Stackapella', (string) ($user->email ?? $user->name), $secret);
    }

    public function verifyTotpCode(string $secret, string $code): bool
    {
        return (bool) $this->google2fa->verifyKey($secret, preg_replace('/\D/', '', $code) ?? '', 1);
    }

    /**
     * Check a TOTP or recovery code, counting failures with a lockout.
     *
     * Each attempt is claimed in one statement before the code is checked, so requests sent in
     * parallel can't share one guess: once admin.max_second_factor_attempts are used, further
     * attempts are refused until the lock (doubling each time, up to an hour) expires, and then
     * one more guess is allowed. After a day without a wrong code the count starts again.
     *
     * @return 'ok'|'invalid'|'locked'
     */
    public function attempt(User $user, string $code, ?string $ip = null): string
    {
        $factor = $user->secondFactor;

        if ($factor === null || ! $factor->hasTotp()) {
            return 'invalid';
        }

        if (! $this->claimAttempt($factor)) {
            return 'locked';
        }

        $code = trim($code);

        if ($this->useTotpCode($factor, $code) || $this->useRecoveryCode($factor, $code)) {
            AdminSecondFactor::query()->whereKey($factor->getKey())->update(['failed_attempts' => 0, 'last_failed_at' => null, 'locked_until' => null]);
            $factor->forceFill(['failed_attempts' => 0, 'locked_until' => null])->syncOriginal();

            return 'ok';
        }

        $attempts = (int) AdminSecondFactor::query()->whereKey($factor->getKey())->value('failed_attempts');
        $max = (int) config('admin.max_second_factor_attempts');
        $locked = $attempts >= $max;

        if ($locked) {
            $until = now()->addMinutes(min(60, 2 ** ($attempts - $max + 2)));
            AdminSecondFactor::query()->whereKey($factor->getKey())->update(['locked_until' => $until]);
            Notification::send($this->admins->all(), new SecondFactorLocked($user));
        }

        $factor->refresh();

        $this->securityLog->record(
            $locked ? SecurityEventType::SecondFactorLocked : SecurityEventType::SecondFactorFailed,
            $user,
            $ip,
            ['attempts' => $attempts, 'method' => 'code'],
        );

        return $locked ? 'locked' : 'invalid';
    }

    /**
     * A failed passkey check is recorded like a wrong code (it doesn't count toward the lock: a
     * passkey can't be guessed).
     */
    public function passkeyFailed(User $user, ?string $ip): void
    {
        $this->securityLog->record(SecurityEventType::SecondFactorFailed, $user, $ip, ['method' => 'passkey']);
    }

    /**
     * Use up one attempt, or refuse while locked. True when this request may check a code.
     */
    private function claimAttempt(AdminSecondFactor $factor): bool
    {
        $now = now();
        $quiet = $now->copy()->subDay();
        $max = (int) config('admin.max_second_factor_attempts');

        // A quiet day since the last wrong code (and no lock in force): the count starts again.
        AdminSecondFactor::query()
            ->whereKey($factor->getKey())
            ->where('last_failed_at', '<', $quiet)
            ->where(fn ($query) => $query->whereNull('locked_until')->orWhere('locked_until', '<=', $now))
            ->update(['failed_attempts' => 0]);

        $claimed = AdminSecondFactor::query()
            ->whereKey($factor->getKey())
            ->where(fn ($query) => $query
                // Not locked, with attempts left...
                ->where(fn ($q) => $q->whereNull('locked_until')->where('failed_attempts', '<', $max))
                // ...or a lock that has run out: one more guess.
                ->orWhere('locked_until', '<=', $now))
            ->update([
                'failed_attempts' => DB::raw('failed_attempts + 1'),
                'last_failed_at' => $now,
                'locked_until' => null,
            ]);

        return $claimed === 1;
    }

    /**
     * Eight new one-time recovery codes. Only their HMACs are stored; the plain codes are shown once.
     *
     * @return list<string>
     */
    public function newRecoveryCodes(AdminSecondFactor $factor): array
    {
        $codes = array_map(static fn (): string => Str::lower(Str::random(5).'-'.Str::random(5)), range(1, 8));
        $factor->forceFill(['recovery_codes' => array_map(self::hashCode(...), $codes)])->save();

        return $codes;
    }

    public function enableTotp(User $user, string $secret): AdminSecondFactor
    {
        $factor = $user->secondFactor ?? new AdminSecondFactor(['user_id' => $user->id]);
        $factor->forceFill(['user_id' => $user->id, 'totp_secret' => $secret, 'totp_confirmed_at' => now(), 'failed_attempts' => 0, 'locked_until' => null])->save();
        $user->setRelation('secondFactor', $factor);

        return $factor;
    }

    private static function hashCode(string $code): string
    {
        return hash_hmac('sha256', Str::lower(trim($code)), (string) config('app.key'));
    }

    /**
     * A TOTP code counts once: its time step must be newer than the last one used, claimed with a
     * conditional update so two requests racing with the same code can't both pass.
     */
    private function useTotpCode(AdminSecondFactor $factor, string $code): bool
    {
        $lastStep = (int) ($factor->totp_last_timestep ?? 0);
        $step = $this->google2fa->verifyKeyNewer((string) $factor->totp_secret, preg_replace('/\D/', '', $code) ?? '', $lastStep, 1);

        // A time step counts up from the Unix epoch, so anything else is no match.
        if (! is_int($step) || $step < 0) {
            return false;
        }

        $claimed = AdminSecondFactor::query()->whereKey($factor->getKey())
            ->where(fn ($query) => $query->whereNull('totp_last_timestep')->orWhere('totp_last_timestep', '<', $step))
            ->update(['totp_last_timestep' => $step]);

        $factor->totp_last_timestep = $step;

        return $claimed === 1;
    }

    /**
     * A recovery code counts once: the row is read again under a lock, so two requests racing
     * with the same code can't both pass.
     */
    private function useRecoveryCode(AdminSecondFactor $factor, string $code): bool
    {
        $code = self::hashCode($code);

        return DB::transaction(function () use ($factor, $code): bool {
            $current = AdminSecondFactor::query()->whereKey($factor->getKey())->lockForUpdate()->first();

            /** @var list<string> $codes */
            $codes = (array) ($current->recovery_codes ?? []);

            foreach ($codes as $index => $stored) {
                if (hash_equals($stored, $code)) {
                    unset($codes[$index]);
                    $current?->forceFill(['recovery_codes' => array_values($codes)])->save();
                    $factor->forceFill(['recovery_codes' => array_values($codes)])->syncOriginalAttribute('recovery_codes');

                    return true;
                }
            }

            return false;
        });
    }
}
