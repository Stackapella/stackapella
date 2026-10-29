<?php

declare(strict_types=1);

namespace App\Http\Requests\Notices;

use Illuminate\Foundation\Http\FormRequest;

/**
 * notices.appeal: why the person thinks it was wrong.
 */
final class AppealRequest extends FormRequest
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
            'text' => ['required', 'string', 'min:2', 'max:5000'],
        ];
    }
}
