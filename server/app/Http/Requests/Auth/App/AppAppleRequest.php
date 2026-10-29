<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth\App;

use App\Rules\OneLine;
use Illuminate\Foundation\Http\FormRequest;

/**
 * auth.app.apple: the identity token and authorisation code, the name Apple gave once, the device.
 */
final class AppAppleRequest extends FormRequest
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
            'identityToken' => ['required', 'string', 'max:8000'],
            // This sign-in's nonce; Apple was given its SHA-256.
            'nonce' => ['required', 'string', 'min:32', 'max:128'],
            'authorizationCode' => ['required', 'string', 'max:2000'],
            'name' => ['nullable', 'string', 'max:80', new OneLine],
            'invite' => ['nullable', 'string', 'max:100'],
            'early' => ['nullable', 'string', 'max:100'],
            'link' => ['nullable', 'boolean'],
            'turnstileToken' => ['nullable', 'string', 'max:4000'],
            ...AppDeviceRules::rules(),
        ];
    }
}
