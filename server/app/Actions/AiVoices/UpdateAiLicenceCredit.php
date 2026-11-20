<?php

declare(strict_types=1);

namespace App\Actions\AiVoices;

use App\Exceptions\Refusal;
use App\Models\AiVoice;
use App\Models\AuditLog;
use App\Models\BetaSinger;
use Illuminate\Support\Facades\DB;

/**
 * beta.aiLicence.update: the credit choice, changed at any time; every label follows at once (TemplateGuide::aiBasedOn).
 */
final class UpdateAiLicenceCredit
{
    public function handle(BetaSinger $singer, bool $credited, ?string $ip = null): AiVoice
    {
        return DB::transaction(function () use ($singer, $credited, $ip): AiVoice {
            $voice = AiVoice::query()->where('beta_singer_id', $singer->id)->whereNull('withdrawn_at')->whereNotNull('accepted_at')->orderByDesc('id')->lockForUpdate()->first();

            if ($voice === null) {
                throw new Refusal(409, 'not_accepted');
            }

            $before = $voice->credited;
            $voice->forceFill(['credited' => $credited, 'based_on' => AcceptAiLicence::name($singer)])->save();
            AuditLog::record('ai.licence_credit', $voice, $singer->user, before: ['credited' => $before], after: ['credited' => $credited], ip: $ip);

            return $voice;
        });
    }
}
