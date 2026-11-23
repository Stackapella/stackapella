<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use App\Rules\JsonSize;
use Illuminate\Foundation\Http\FormRequest;

/**
 * account.learning.update: the course's progress object, with its version.
 */
final class LearningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'changeId' => ['required', 'uuid'],
            'version' => ['required', 'integer', 'min:0'],
            // The epoch the device's progress is from (0, or none from an older app, before any reset).
            'epoch' => ['sometimes', 'integer', 'min:0'],
            'progress' => ['present', 'array', new JsonSize(256)],
            // A part's plan for a rehearsal date (docs/rehearsal-ready-plan.md): its shape only; the app merges it.
            'progress.rehearsals' => ['sometimes', 'array', 'list', 'max:20'],
            'progress.rehearsals.*' => ['array:songId,partKey,date,madeAt,removedAt,groupId,readyAt,days'],
            'progress.rehearsals.*.songId' => ['required', 'string', 'max:120'],
            'progress.rehearsals.*.partKey' => ['required', 'string', 'max:40'],
            'progress.rehearsals.*.date' => ['required', 'date_format:Y-m-d'],
            'progress.rehearsals.*.madeAt' => ['required', 'integer', 'min:0'],
            'progress.rehearsals.*.removedAt' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'progress.rehearsals.*.readyAt' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'progress.rehearsals.*.groupId' => ['sometimes', 'nullable', 'string', 'max:40'],
            'progress.rehearsals.*.days' => ['required', 'array', 'list', 'max:60'],
            'progress.rehearsals.*.days.*' => ['array:date,phrases,whole,doneAt'],
            'progress.rehearsals.*.days.*.date' => ['required', 'date_format:Y-m-d'],
            'progress.rehearsals.*.days.*.phrases' => ['required', 'array', 'list', 'size:2'],
            'progress.rehearsals.*.days.*.phrases.*' => ['integer', 'min:0', 'max:999'],
            'progress.rehearsals.*.days.*.whole' => ['sometimes', 'boolean'],
            'progress.rehearsals.*.days.*.doneAt' => ['sometimes', 'nullable', 'integer', 'min:0'],
            // The first week's note inside the chord (docs/first-week-plan.md change 2): numbers only, never audio.
            'progress.chordAgain' => ['sometimes', 'array', 'list', 'max:14'],
            'progress.chordAgain.*' => ['array:date,inTuneSeconds,centsOff'],
            'progress.chordAgain.*.date' => ['required', 'date_format:Y-m-d', 'distinct'],
            'progress.chordAgain.*.inTuneSeconds' => ['required', 'numeric', 'between:0,5'],
            'progress.chordAgain.*.centsOff' => ['required', 'numeric', 'between:0,1200'],
        ];
    }

    /**
     * The progress as sent, whole (the server keeps it as sent): the rules above check its size and the shape of the
     * keys the server knows, while validated('progress') would keep only those keys.
     *
     * @return array<string, mixed>
     */
    public function progress(): array
    {
        $this->validated();

        return (array) $this->input('progress');
    }
}
