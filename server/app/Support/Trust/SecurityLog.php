<?php

declare(strict_types=1);

namespace App\Support\Trust;

use App\Enums\SecurityEventType;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Writes security_events: what the trust and safety screen and the alerts read.
 */
final class SecurityLog
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function record(SecurityEventType $type, ?User $user, ?string $ip, array $context = []): SecurityEvent
    {
        $event = new SecurityEvent;
        $event->forceFill([
            'type' => $type,
            'user_id' => $user?->getKey(),
            'ip' => $ip,
            'ip_hash' => $ip !== null ? IpHash::of($ip) : null,
            'context' => $context === [] ? null : $context,
        ])->save();

        return $event;
    }

    /**
     * A limiter refusal, written once an hour per limiter and person, so a flood of refusals is
     * one row rather than thousands. The hourly count still goes up every time, for the alert.
     */
    public function rateLimited(string $limiter, string $rule, ?User $user, string $ip): void
    {
        $hour = now()->format('YmdH');
        Cache::add('security:refusals:'.$hour, 0, 7200);
        Cache::increment('security:refusals:'.$hour);

        $who = $user !== null ? 'u'.$user->getKey() : IpHash::daily($ip);

        if (Cache::add("security:rate-limited:{$limiter}:{$who}", true, 3600)) {
            $this->record(SecurityEventType::RateLimited, $user, $ip, ['limiter' => $limiter, 'rule' => $rule]);
        }
    }
}
