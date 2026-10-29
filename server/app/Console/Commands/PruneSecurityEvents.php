<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SecurityEvent;
use Illuminate\Console\Command;

/**
 * Raw IP addresses are kept 30 days, then only their HMAC; rows go after a year
 * (docs/trust-and-safety-plan.md, "Privacy, kept").
 */
final class PruneSecurityEvents extends Command
{
    protected $signature = 'app:prune-security-events';

    protected $description = 'Forget raw IPs older than 30 days, and events older than a year';

    public function handle(): int
    {
        $forgotten = SecurityEvent::query()
            ->whereNotNull('ip')
            ->where('created_at', '<', now()->subDays(SecurityEvent::RAW_IP_DAYS))
            ->update(['ip' => null]);

        $deleted = SecurityEvent::query()
            ->where('created_at', '<', now()->subDays(SecurityEvent::KEEP_DAYS))
            ->delete();

        $this->info("Forgot {$forgotten} raw IPs; deleted {$deleted} old events.");

        return self::SUCCESS;
    }
}
