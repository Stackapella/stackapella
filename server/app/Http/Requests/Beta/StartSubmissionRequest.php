<?php

declare(strict_types=1);

namespace App\Http\Requests\Beta;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * beta.submissions.create: what the device measured. The numbers must add up: a 16-bit mono WAV
 * of `frames` (a 44-byte header and 2 bytes a frame), at a browser's sample rate, at most 10
 * minutes.
 */
final class StartSubmissionRequest extends FormRequest
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
            'assignmentId' => ['required', 'string', 'max:32'],
            // A song's part names its version and key; a course clip has neither (StartSubmission says).
            'templateVersion' => ['nullable', 'integer', 'min:1'],
            'key' => ['nullable', 'string', 'max:8'],
            'sampleRate' => ['required', 'integer', 'in:'.implode(',', (array) config('beta.sample_rates'))],
            'frames' => ['required', 'integer', 'min:1'],
            'latencyFrames' => ['required', 'integer', 'min:-100000', 'max:100000'],
            'bytes' => ['required', 'integer', 'min:46'],
            'sha256' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/i'],
            'device' => ['nullable', 'string', 'max:200'],
            'mic' => ['nullable', 'string', 'max:200'],
            'scores' => ['nullable', 'array'],
            // The take's verdict as the singer saw it (docs/decisions.md, "beta singers' scores").
            'scores.pitchPercent' => ['nullable', 'numeric', 'between:0,100'],
            'scores.timingPercent' => ['nullable', 'numeric', 'between:0,100'],
            'scores.verdict' => ['nullable', 'string', 'in:ready'],
            'scores.tries' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $rate = (int) $this->input('sampleRate');
                $frames = (int) $this->input('frames');

                if ($rate > 0 && $frames > (int) config('beta.max_seconds') * $rate) {
                    $validator->errors()->add('frames', __('validation.take_too_long'));
                }

                // The canonical 44-byte header, then 2 bytes a frame. Anything else isn't the take described.
                if ((int) $this->input('bytes') !== 44 + 2 * $frames) {
                    $validator->errors()->add('bytes', __('validation.take_bytes'));
                }

                if (is_array($this->input('scores')) && strlen((string) json_encode($this->input('scores'))) > 20000) {
                    $validator->errors()->add('scores', __('validation.take_scores'));
                }
            },
        ];
    }
}
