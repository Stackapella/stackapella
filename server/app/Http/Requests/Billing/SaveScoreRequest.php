<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use App\Rules\JsonSize;
use Illuminate\Foundation\Http\FormRequest;

/**
 * scores.create: an exercise's score summary.
 */
final class SaveScoreRequest extends FormRequest
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
            'id' => ['required', 'uuid'],
            'lesson' => ['nullable', 'string', 'max:60'],
            'templateId' => ['nullable', 'string', 'max:80'],
            'templateVersion' => ['nullable', 'integer', 'min:1'],
            'partKey' => ['nullable', 'string', 'max:40'],
            'recordedAt' => ['required', 'date'],
            'summary' => ['present', 'array', new JsonSize(16)],
        ];
    }
}
