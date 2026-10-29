<?php

declare(strict_types=1);

namespace App\Http\Requests\Ideas;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ideas.list: open or complete, a part still needed, a page.
 */
final class IdeasListRequest extends FormRequest
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
            'tab' => ['sometimes', 'nullable', 'string', 'in:open,complete'],
            'needs' => ['sometimes', 'nullable', 'string', 'max:40'],
            'cursor' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
