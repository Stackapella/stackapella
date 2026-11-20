<?php

declare(strict_types=1);

namespace App\Actions\AiVoices;

use App\Exceptions\Refusal;
use App\Models\AiVoice;
use App\Models\AuditLog;
use App\Models\BetaSinger;
use App\Support\AiVoices\AiLicence;
use Illuminate\Support\Facades\DB;

/**
 * beta.aiLicence.accept: the singer accepts the current terms (docs/ai-voices-build-plan.md, phase 1b). Their live AI
 * voice records it (the version, when, the credit choice); without one, a new voice is made, as after a withdrawal.
 * Its label names them by their account's name, when they chose to be credited.
 */
final class AcceptAiLicence
{
    public function handle(BetaSinger $singer, int $version, bool $credited, ?string $ip = null): AiVoice
    {
        if (! AiLicence::offered($singer)) {
            throw new Refusal(403, 'not_offered');
        }

        if ($version !== AiLicence::current()) {
            throw new Refusal(409, 'agreement_changed');
        }

        return DB::transaction(function () use ($singer, $version, $credited, $ip): AiVoice {
            // Read again under the lock that forgetting and revoking take: a singer forgotten or revoked meanwhile accepts nothing.
            $locked = BetaSinger::query()->with('user')->whereKey($singer->id)->lockForUpdate()->first();

            if ($locked === null || ! AiLicence::offered($locked)) {
                throw new Refusal(403, 'not_offered');
            }

            $singer = $locked;
            $voice = AiVoice::query()->where('beta_singer_id', $singer->id)->whereNull('withdrawn_at')->orderByDesc('id')->lockForUpdate()->first();
            $before = $voice === null ? null : ['version' => $voice->consent_version, 'credited' => $voice->credited];
            $voice ??= (new AiVoice(['name' => mb_substr($singer->name !== '' ? $singer->name : 'Singer '.$singer->sqid(), 0, 80)]))->forceFill(['beta_singer_id' => $singer->id]);

            $voice->forceFill([
                'based_on' => self::name($singer),
                'consent_date' => now()->toDateString(),
                'consent_version' => (string) $version,
                'accepted_at' => now(),
                'credited' => $credited,
            ])->save();

            AuditLog::record('ai.licence_accepted', $voice, $singer->user, before: $before, after: ['version' => $version, 'credited' => $credited, 'singer' => $singer->id], ip: $ip);

            return $voice;
        });
    }

    /** The name the label gives, when they chose a credit: their account's name when they accepted or last changed it. */
    public static function name(BetaSinger $singer): ?string
    {
        $name = $singer->user !== null ? trim($singer->user->name) : '';

        return $name !== '' ? mb_substr($name, 0, 120) : null;
    }
}
