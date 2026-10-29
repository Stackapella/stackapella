<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A WebAuthn credential from the browser (PublicKeyCredential.toJSON()), checked by
 * laravel/passkeys against the options this session was given.
 */
final class PasskeyCredentialRequest extends FormRequest
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
            'credential' => ['required', 'array'],
            'name' => ['nullable', 'string', 'max:60'],
        ];
    }
}
