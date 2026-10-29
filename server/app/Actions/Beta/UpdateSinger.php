<?php

declare(strict_types=1);

namespace App\Actions\Beta;

use App\Models\AuditLog;
use App\Models\BetaSinger;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Hillel changes a singer's details: only the fields given, audit-logged by name (not value).
 */
final class UpdateSinger
{
    private const FIELDS = ['name' => 'name', 'email' => 'email', 'note' => 'note', 'expiresAt' => 'expires_at', 'hourlyRateCents' => 'hourly_rate_cents', 'currency' => 'currency'];

    /**
     * @param  array<string, mixed>  $changes  validated, in the contract's names
     */
    public function handle(BetaSinger $singer, array $changes, User $actor, ?string $ip = null): BetaSinger
    {
        $fields = [];

        foreach (self::FIELDS as $from => $to) {
            if (! array_key_exists($from, $changes)) {
                continue;
            }

            $value = $changes[$from];
            $fields[$to] = match ($from) {
                'expiresAt' => is_string($value) ? Carbon::parse($value)->utc() : null,
                'currency' => is_string($value) ? strtoupper($value) : null,
                default => $value,
            };
        }

        $singer->fill($fields)->save();
        AuditLog::record('beta.singer_updated', $singer, $actor, after: ['fields' => array_keys($fields)], ip: $ip);

        return $singer;
    }
}
