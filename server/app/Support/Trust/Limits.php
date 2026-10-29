<?php

declare(strict_types=1);

namespace App\Support\Trust;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Support\Http\ApiError;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The named limiters (config/limits.php) with Hillel's live overrides from the settings table.
 * AppServiceProvider registers each one with RateLimiter::for(), so routes use `throttle:<name>`.
 */
final class Limits
{
    private const OVERRIDES_CACHE = 'limits:overrides';

    public function __construct(private readonly SecurityLog $securityLog, private readonly TrustLevel $trust) {}

    /**
     * Every limiter's rules, each with its default and its override (null when there is none).
     *
     * @return array<string, array<string, array{max: int, per: string, by: string, when: string|null, default: int, override: int|null}>>
     */
    public function all(): array
    {
        $overrides = $this->overrides();

        $all = [];

        /** @var array<string, array<string, array{max: int, per: string, by: string, when?: string}>> $config */
        $config = (array) config('limits');

        foreach ($config as $limiter => $rules) {
            foreach ($rules as $name => $rule) {
                $override = $overrides["{$limiter}.{$name}"] ?? null;
                $all[$limiter][$name] = [
                    'max' => $override ?? $rule['max'],
                    'per' => $rule['per'],
                    'by' => $rule['by'],
                    'when' => $rule['when'] ?? null,
                    'default' => $rule['max'],
                    'override' => $override,
                ];
            }
        }

        return $all;
    }

    /**
     * One rule's max, with admin's override (for the limits counted by hand).
     */
    public function max(string $limiter, string $rule): int
    {
        return $this->all()[$limiter][$rule]['max'] ?? throw new InvalidArgumentException("No rule [{$limiter}.{$rule}] in config/limits.php.");
    }

    /**
     * The Limit objects for one request: one per rule that applies to it.
     *
     * @return list<Limit>
     */
    public function for(string $limiter, Request $request): array
    {
        $rules = $this->all()[$limiter] ?? throw new InvalidArgumentException("No limiter named [{$limiter}] in config/limits.php.");
        $user = $request->user();
        $user = $user instanceof User ? $user : null;
        $limits = [];

        foreach ($rules as $name => $rule) {
            if (! $this->applies($rule['when'], $user)) {
                continue;
            }

            $limit = match ($rule['per']) {
                'minute' => Limit::perMinute($rule['max']),
                'hour' => Limit::perHour($rule['max']),
                'day' => Limit::perDay($rule['max']),
                default => throw new InvalidArgumentException("Unknown period [{$rule['per']}] for {$limiter}.{$name}."),
            };

            $limits[] = $limit
                ->by("{$limiter}:{$name}:".$this->key($rule['by'], $request, $user))
                ->response(fn (Request $request, array $headers): Response => $this->refuse($limiter, $name, $request, $user, $headers));
        }

        return $limits;
    }

    /**
     * Count one attempt by hand, for an action that decides itself what counts (a share only when
     * a harmony becomes unlisted). Null when it's allowed, or the seconds to wait (logged as a
     * refusal, as the route limiters' are).
     */
    public function attempt(string $limiter, User $user, string $ip): ?int
    {
        $rules = $this->all()[$limiter] ?? throw new InvalidArgumentException("No limiter named [{$limiter}] in config/limits.php.");
        $keys = [];

        foreach ($rules as $name => $rule) {
            if (! $this->applies($rule['when'], $user)) {
                continue;
            }

            $key = "{$limiter}:{$name}:u{$user->getKey()}";
            $seconds = match ($rule['per']) {
                'minute' => 60,
                'hour' => 3600,
                'day' => 86400,
                default => throw new InvalidArgumentException("Unknown period [{$rule['per']}] for {$limiter}.{$name}."),
            };

            if (RateLimiter::tooManyAttempts($key, $rule['max'])) {
                $this->securityLog->rateLimited($limiter, $name, $user, $ip);

                return max(1, RateLimiter::availableIn($key));
            }

            $keys[$key] = $seconds;
        }

        foreach ($keys as $key => $seconds) {
            RateLimiter::hit($key, $seconds);
        }

        return null;
    }

    /**
     * Change one rule's maximum live, or put it back to the default with null.
     */
    public function override(string $limiter, string $rule, ?int $max, User $actor, string $reason, ?string $ip = null): void
    {
        $current = $this->all()[$limiter][$rule] ?? throw new InvalidArgumentException("No rule {$limiter}.{$rule}.");
        $key = "limits.{$limiter}.{$rule}";

        DB::transaction(function () use ($key, $max, $actor, $reason, $current, $ip): void {
            if ($max === null) {
                Setting::query()->where('key', $key)->delete();
            } else {
                // One statement, so two changes at the same moment can't both insert the row.
                Setting::query()->upsert(
                    [['key' => $key, 'value' => json_encode($max), 'reason' => $reason, 'updated_by' => $actor->getKey(), 'created_at' => now(), 'updated_at' => now()]],
                    ['key'],
                    ['value', 'reason', 'updated_by', 'updated_at'],
                );
            }

            AuditLog::record('limit.'.$key, null, $actor, before: ['max' => $current['max']], after: ['max' => $max ?? $current['default']], reason: $reason, ip: $ip);
        });

        Cache::forget(self::OVERRIDES_CACHE);
    }

    /**
     * Who set each override, when and why, for the admin (one query, people eager loaded).
     *
     * @return array<string, Setting>
     */
    public function changes(): array
    {
        return Setting::query()
            ->with('updater')
            ->where('key', 'like', 'limits.%')
            ->get()
            ->keyBy(static fn (Setting $setting): string => substr($setting->key, strlen('limits.')))
            ->all();
    }

    private function applies(?string $when, ?User $user): bool
    {
        if ($when === null) {
            return true;
        }

        if (str_starts_with($when, 'level:')) {
            return $user !== null && $this->trust->of($user) === (int) substr($when, strlen('level:'));
        }

        return ($when === 'signed_in' && $user !== null) || ($when === 'signed_out' && $user === null);
    }

    /**
     * @return array<string, int>
     */
    private function overrides(): array
    {
        /** @var array<string, int> */
        return Cache::rememberForever(self::OVERRIDES_CACHE, static fn (): array => Setting::query()
            ->where('key', 'like', 'limits.%')
            ->pluck('value', 'key')
            ->mapWithKeys(static fn (mixed $value, string $key): array => [substr($key, strlen('limits.')) => (int) $value])
            ->all());
    }

    private function key(string $by, Request $request, ?User $user): string
    {
        $ip = (string) $request->ip();
        // Only a string is an email: an array (?email[]=x) or nothing counts by the IP instead.
        $email = static fn (): string => is_string($value = $request->input('email')) ? trim($value) : '';

        return match ($by) {
            // A signed-out beta singer counts as themselves (per invite), not by IP.
            'account' => $user !== null ? 'u'.$user->getKey() : 'ip'.IpHash::of($ip),
            'ip' => IpHash::of($ip),
            'email' => $email() !== '' ? IpHash::email($email()) : 'ip'.IpHash::of($ip),
            'ip_and_email' => IpHash::of($ip).':'.IpHash::email($email()),
            default => throw new InvalidArgumentException("Unknown key [{$by}]."),
        };
    }

    /**
     * @param  array<string, string|int>  $headers
     */
    private function refuse(string $limiter, string $rule, Request $request, ?User $user, array $headers): Response
    {
        $this->securityLog->rateLimited($limiter, $rule, $user, (string) $request->ip());
        $retryAfter = (int) ($headers['Retry-After'] ?? 60);
        $headers = array_map('strval', $headers);

        if ($request->is('api/*') || $request->expectsJson()) {
            return ApiError::response(429, 'rate_limited', ['retryAfter' => $retryAfter], $headers);
        }

        return response()->view('errors.429', ['minutes' => (int) ceil($retryAfter / 60)], 429, $headers);
    }
}
