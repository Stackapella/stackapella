<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Beta;

use App\Rules\ValidCursor;
use Illuminate\Foundation\Http\FormRequest;

final class ListFeedbackRequest extends FormRequest
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
            'unread' => ['nullable', 'boolean'],
            'cursor' => ['nullable', 'string', 'max:500', new ValidCursor],
        ];
    }
}
