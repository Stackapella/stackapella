<?php

declare(strict_types=1);

namespace App\Http\Requests\Coaching;

use App\Rules\NoLinks;
use Illuminate\Foundation\Http\FormRequest;

/**
 * coach.application.create: the answers, a sample review, the agreement, and 18 and over.
 */
final class ApplyRequest extends FormRequest
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
            'experience' => ['required', 'string', 'min:20', 'max:2000', new NoLinks],
            'training' => ['required', 'string', 'max:2000', new NoLinks],
            'voiceTypes' => ['required', 'array', 'min:1', 'max:6'],
            'voiceTypes.*' => ['string', 'in:soprano,mezzo,alto,tenor,baritone,bass'],
            'styles' => ['required', 'array', 'min:1', 'max:10'],
            'styles.*' => ['string', 'max:40'],
            'languages' => ['required', 'array', 'min:1', 'max:10'],
            'languages.*' => ['string', 'regex:/^[a-z]{2,3}$/'],
            'timezone' => ['required', 'timezone:all'],
            'introUrl' => ['nullable', 'url:https', 'max:500'],
            'sampleReview' => ['required', 'array'],
            'sampleReview.comments' => ['required', 'array', 'min:1', 'max:100'],
            'sampleReview.summary' => ['required', 'array'],
            'sampleReview.summary.working' => ['required', 'string', 'max:1000'],
            'sampleReview.summary.toWorkOn' => ['required', 'array', 'min:1', 'max:3'],
            'sampleReview.summary.practise' => ['required', 'string', 'max:1000'],
            'agreementVersion' => ['required', 'integer', 'in:'.(int) config('coaching.agreement_version')],
            'adult' => ['required', 'accepted'],
        ];
    }
}
