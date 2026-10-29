<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth\App;

use Illuminate\Foundation\Http\FormRequest;

/**
 * auth.app.refresh: the refresh token.
 */
final class AppRefreshRequest extends FormRequest
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
            'refreshToken' => ['required', 'string', 'max:200'],
        ];
    }
}
