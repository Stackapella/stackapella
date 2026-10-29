<?php

declare(strict_types=1);

namespace App\Http\Requests\Dev;

use Illuminate\Foundation\Http\FormRequest;

final class DevLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app()->environment('local');
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'intended' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
