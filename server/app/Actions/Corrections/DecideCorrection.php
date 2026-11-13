<?php

declare(strict_types=1);

namespace App\Actions\Corrections;

use App\Enums\CorrectionState;
use App\Exceptions\Refusal;
use App\Models\Correction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The team's decision on a correction (docs/corrections-plan.md): from new, accepted or rejected; from accepted, done
 * or rejected; a rejection says why.
 */
final class DecideCorrection
{
    public function handle(User $by, string $sqid, CorrectionState $to, ?string $note): Correction
    {
        return DB::transaction(function () use ($by, $sqid, $to, $note): Correction {
            $correction = Correction::query()->whereSqid($sqid)->lockForUpdate()->first() ?? throw new Refusal(404, 'not_found');
            $note = $note !== null && trim($note) !== '' ? trim($note) : null;

            if (! in_array($to, $correction->state->next(), true)) {
                throw new Refusal(409, 'not_allowed');
            }
            if ($to === CorrectionState::Rejected && $note === null) {
                throw ValidationException::withMessages(['note' => __('validation.correction_reject_note')]);
            }

            $correction->forceFill(['state' => $to, 'note' => $note, 'decided_by' => $by->id, 'decided_at' => now()])->save();

            return $correction->load('decider');
        });
    }
}
