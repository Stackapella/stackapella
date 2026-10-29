<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * auth.signin.
 */
final class SignInRequest extends FormRequest
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
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:200'],
            'remember' => ['nullable', 'boolean'],
            'turnstileToken' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
