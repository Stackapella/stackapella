<?php

declare(strict_types=1);

namespace App\Http\Requests\Beta;

use App\Support\Templates\TemplateRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * beta.feedback.create: Tell Hillel.
 */
final class FeedbackRequest extends FormRequest
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
            'text' => ['required', 'string', 'max:2000'],
            'templateId' => ['nullable', 'string', 'max:80'],
            'partKey' => ['nullable', 'string', Rule::in([...TemplateRules::OFFICIAL_KEYS, ...TemplateRules::BARBERSHOP_KEYS])],
            'beat' => ['nullable', 'numeric', 'min:0', 'max:99999'],
        ];
    }
}
