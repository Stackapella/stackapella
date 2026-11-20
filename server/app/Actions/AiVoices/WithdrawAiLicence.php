<?php

declare(strict_types=1);

namespace App\Actions\AiVoices;

use App\Exceptions\Refusal;
use App\Models\AiVoice;
use App\Models\BetaSinger;

/**
 * beta.aiLicence.withdraw: the singer takes it back in the app. Their voice goes the way Admin's withdraw takes it
 * (WithdrawAiVoice): every part it sang retired at once, purged from the CDN, its masters deleted.
 */
final class WithdrawAiLicence
{
    public function __construct(private readonly WithdrawAiVoice $withdraw) {}

    /**
     * @return int how many parts were retired
     */
    public function handle(BetaSinger $singer, ?string $ip = null): int
    {
        $voice = AiVoice::query()->where('beta_singer_id', $singer->id)->whereNull('withdrawn_at')->whereNotNull('accepted_at')->orderByDesc('id')->first();

        if ($voice === null) {
            throw new Refusal(409, 'not_accepted');
        }

        try {
            return $this->withdraw->handle($voice, $singer->user, $ip);
        } catch (Refusal $refusal) {
            // Withdrawn a moment ago (a second tap, or Admin at the same time): nothing left to withdraw.
            throw $refusal->refusalCode === 'already_withdrawn' ? new Refusal(409, 'not_accepted') : $refusal;
        }
    }
}
