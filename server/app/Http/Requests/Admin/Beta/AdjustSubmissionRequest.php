<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Beta;

use App\Models\BetaSubmission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * admin.beta.submissions.adjust: the light fixes applied when the take is published.
 */
final class AdjustSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'offsetMs' => ['sometimes', 'integer', 'min:-500', 'max:500'],
            'trimStartMs' => ['sometimes', 'integer', 'min:0', 'max:60000'],
            'trimEndMs' => ['sometimes', 'integer', 'min:0', 'max:60000'],
            'gainDb' => ['sometimes', 'numeric', 'min:-12', 'max:12'],
        ];
    }

    /**
     * The trims and the nudge must leave at least a second of the take to publish.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $submission = $this->route('submission');

                if (! $submission instanceof BetaSubmission) {
                    return;
                }

                $start = (int) $this->input('trimStartMs', $submission->trim_start_ms);
                $end = (int) $this->input('trimEndMs', $submission->trim_end_ms);
                $nudge = abs((int) $this->input('offsetMs', $submission->offset_ms));
                $left = (int) floor($submission->durationSeconds() * 1000) - $start - $end - $nudge;

                if ($left < 1000) {
                    $validator->errors()->add('trimEndMs', __('validation.trims_too_long'));
                }
            },
        ];
    }
}
