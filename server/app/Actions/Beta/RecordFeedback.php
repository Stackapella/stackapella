<?php

declare(strict_types=1);

namespace App\Actions\Beta;

use App\Models\BetaFeedback;
use App\Models\BetaSinger;

/**
 * "Tell Hillel": a singer's note, with where in the song it's about.
 */
final class RecordFeedback
{
    public function handle(BetaSinger $singer, string $text, ?string $templateSlug, ?string $partKey, ?float $beat): BetaFeedback
    {
        $feedback = new BetaFeedback(['text' => $text, 'template_slug' => $templateSlug, 'part_key' => $partKey, 'beat' => $beat]);
        $feedback->forceFill(['beta_singer_id' => $singer->id])->save();

        return $feedback;
    }
}
