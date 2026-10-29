<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth\App;

use Illuminate\Foundation\Http\FormRequest;

/**
 * auth.app.google: the device's Google ID token, the device, and what the web's sign-in carries.
 */
final class AppGoogleRequest extends FormRequest
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
            'idToken' => ['required', 'string', 'max:8000'],
            // The app's nonce for this launch (Google's SDK takes one per launch), when it set one.
            'nonce' => ['nullable', 'string', 'min:32', 'max:128'],
            'invite' => ['nullable', 'string', 'max:100'],
            'early' => ['nullable', 'string', 'max:100'],
            'link' => ['nullable', 'boolean'],
            'turnstileToken' => ['nullable', 'string', 'max:4000'],
            ...AppDeviceRules::rules(),
        ];
    }
}
