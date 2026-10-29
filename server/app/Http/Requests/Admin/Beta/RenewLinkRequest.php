<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Beta;

use Illuminate\Foundation\Http\FormRequest;

final class RenewLinkRequest extends FormRequest
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
            'expiresAt' => ['nullable', 'date', 'after:now'],
        ];
    }
}
