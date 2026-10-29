<?php

declare(strict_types=1);

namespace App\Http\Requests\Coaching;

use Illuminate\Foundation\Http\FormRequest;

/**
 * coaches.list: the directory's filters, an order, a page.
 */
final class CoachesListRequest extends FormRequest
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
            'voiceType' => ['sometimes', 'nullable', 'string', 'max:20'],
            'style' => ['sometimes', 'nullable', 'string', 'max:40'],
            'language' => ['sometimes', 'nullable', 'string', 'max:10'],
            'maxPrice' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10000000'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'turnaround' => ['sometimes', 'nullable', 'integer', 'between:1,60'],
            'lessons' => ['sometimes', 'nullable', 'boolean'],
            'rating' => ['sometimes', 'nullable', 'numeric', 'between:1,5'],
            'song' => ['sometimes', 'nullable', 'string', 'max:80'],
            'sort' => ['sometimes', 'nullable', 'string', 'in:suggested,rating,price,soonest'],
            'tab' => ['sometimes', 'nullable', 'string', 'in:featured'],
            'cursor' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:10000'],
        ];
    }
}
