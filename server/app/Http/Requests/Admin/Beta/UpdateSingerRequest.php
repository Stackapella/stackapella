<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Beta;

use Illuminate\Foundation\Http\FormRequest;

/**
 * admin.beta.singers.update: any of the singer's details.
 */
final class UpdateSingerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'expiresAt' => ['sometimes', 'nullable', 'date', 'after:now'],
            'hourlyRateCents' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000000'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3', 'alpha'],
        ];
    }
}
