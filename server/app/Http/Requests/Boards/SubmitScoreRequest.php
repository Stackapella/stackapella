<?php

declare(strict_types=1);

namespace App\Http\Requests\Boards;

use Illuminate\Foundation\Http\FormRequest;

/**
 * boards.scores.submit: a take's score on a song's part, with how it was sung and the scorer's
 * versions (contract/api.md).
 */
final class SubmitScoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'templateId' => ['required', 'string', 'max:80'],
            'templateVersion' => ['required', 'integer', 'min:1'],
            'partKey' => ['required', 'string', 'max:32'],
            'scoringVersion' => ['required', 'integer', 'min:1'],
            'pitchDetectorVersion' => ['required', 'integer', 'min:1'],
            'score' => ['required', 'integer', 'min:0', 'max:100'],
            'mode' => ['required', 'string', 'max:16'],
            'keyOffset' => ['required', 'integer', 'between:-12,12'],
            'speed' => ['required', 'numeric', 'between:0.25,2'],
            'guideMuted' => ['required', 'boolean'],
            'timingChecked' => ['required', 'boolean'],
            'output' => ['nullable', 'in:speaker,wired,bluetooth,unknown'],
            'takeId' => ['nullable', 'uuid'],
            'appVersion' => ['nullable', 'string', 'max:32'],
            'alignmentMs' => ['nullable', 'integer', 'between:-5000,5000'],
            'notes' => ['nullable', 'array', 'max:2000'],
            'notes.*.lit' => ['required', 'boolean'],
            'notes.*.cents' => ['nullable', 'integer', 'between:-2400,2400'],
            'notes.*.timingMs' => ['nullable', 'integer', 'between:-10000,10000'],
        ];
    }
}
