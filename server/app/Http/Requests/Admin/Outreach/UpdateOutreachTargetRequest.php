<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Outreach;

use App\Models\OutreachTarget;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateOutreachTargetRequest extends FormRequest
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
            ...OutreachRules::target(partial: true),
            'status' => ['sometimes', 'string', 'in:'.implode(',', OutreachTarget::STATUSES)],
            'outcome' => ['sometimes', 'nullable', 'string', 'in:'.implode(',', OutreachTarget::OUTCOMES)],
            'outcomeNote' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
