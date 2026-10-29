<?php

declare(strict_types=1);

namespace App\Actions\Beta;

use App\Models\AuditLog;
use App\Models\BetaAssignment;
use App\Models\BetaSinger;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Invite a singer: a link that attaches the singer record to their own account (once, for 14
 * days), the parts they're asked to record, and their agreed rate. The app sends nothing: Hillel copies the link and sends it himself.
 */
final class InviteSinger
{
    /**
     * @param  list<array{template_id: int, part_key: string, minutes: int|null}>  $parts
     * @return array{singer: BetaSinger, token: string}
     */
    public function handle(string $name, ?string $email, ?string $note, ?CarbonInterface $expiresAt, ?int $hourlyRateCents, ?string $currency, array $parts, User $actor, ?string $ip = null): array
    {
        $token = self::newToken();

        $singer = DB::transaction(function () use ($name, $email, $note, $expiresAt, $hourlyRateCents, $currency, $parts, $actor, $ip, $token): BetaSinger {
            $singer = new BetaSinger([
                'name' => $name,
                'email' => $email,
                'note' => $note,
                'expires_at' => $expiresAt ?? now()->addDays(self::DAYS),
                'hourly_rate_cents' => $hourlyRateCents,
                'currency' => $currency !== null ? strtoupper($currency) : null,
            ]);
            $singer->forceFill(['token_hash' => hash('sha256', $token), 'created_by' => $actor->getKey()])->save();

            foreach ($parts as $part) {
                $singer->assignments()->save(new BetaAssignment($part));
            }

            AuditLog::record('beta.invited', $singer, $actor, after: ['parts' => count($parts)], ip: $ip);

            return $singer;
        });

        return ['singer' => $singer, 'token' => $token];
    }

    /** How long an invite can be accepted, unless Hillel sets otherwise. */
    public const DAYS = 14;

    /**
     * 40 random characters: unguessable, and safe in a URL.
     */
    public static function newToken(): string
    {
        return Str::random(40);
    }
}
