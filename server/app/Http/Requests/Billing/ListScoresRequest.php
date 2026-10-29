<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use App\Rules\ValidCursor;
use Illuminate\Foundation\Http\FormRequest;

/**
 * scores.list's filters.
 */
final class ListScoresRequest extends FormRequest
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
            'templateId' => ['nullable', 'string', 'max:80'],
            'partKey' => ['nullable', 'string', 'max:40'],
            'lesson' => ['nullable', 'string', 'max:60'],
            'since' => ['nullable', 'date'],
            'cursor' => ['nullable', 'string', 'max:500', new ValidCursor],
        ];
    }
}
