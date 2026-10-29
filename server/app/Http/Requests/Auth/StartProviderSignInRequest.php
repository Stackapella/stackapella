<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class StartProviderSignInRequest extends FormRequest
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
            'intended' => ['nullable', 'string', 'max:2000'],
            'link' => ['nullable', 'boolean'],
            'ref' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9-]+$/'],
            // A beta invite: lets this sign-up through paused sign-ups, and is accepted after.
            'invite' => ['nullable', 'string', 'max:100'],
            'early' => ['nullable', 'string', 'max:100'],
            // The honeypot (docs/conventions.md): a person never sees or fills it.
            'nickname' => ['prohibited'],
        ];
    }
}
