<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth\App;

use Illuminate\Foundation\Http\FormRequest;

/**
 * auth.app.signin: an address, its password, and the device.
 */
final class AppSignInRequest extends FormRequest
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
            'password' => ['required', 'string', 'max:1000'],
            'turnstileToken' => ['nullable', 'string', 'max:4000'],
            ...AppDeviceRules::rules(),
        ];
    }
}
