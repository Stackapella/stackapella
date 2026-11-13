<?php

declare(strict_types=1);

namespace App\Http\Requests\Corrections;

use App\Enums\CorrectionState;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * admin.corrections.update: the team's decision (editors, by the route's middleware).
 */
final class UpdateCorrectionRequest extends FormRequest
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
            'state' => ['required', Rule::in([CorrectionState::Accepted->value, CorrectionState::Done->value, CorrectionState::Rejected->value])],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
