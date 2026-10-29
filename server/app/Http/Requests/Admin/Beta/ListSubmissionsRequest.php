<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Beta;

use App\Enums\SubmissionStatus;
use App\Rules\ValidCursor;
use App\Support\Templates\TemplateRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListSubmissionsRequest extends FormRequest
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
            'status' => ['nullable', Rule::in(['waiting', ...array_column(SubmissionStatus::cases(), 'value')])],
            'template' => ['nullable', 'string', 'max:80'],
            'singer' => ['nullable', 'string', 'max:40'],
            'part' => ['nullable', 'string', Rule::in([...TemplateRules::OFFICIAL_KEYS, ...TemplateRules::BARBERSHOP_KEYS])],
            'cursor' => ['nullable', 'string', 'max:500', new ValidCursor],
        ];
    }
}
