<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * auth.link.send: the address, where to go after, and what opens paused sign-ups.
 */
final class SendSignInLinkRequest extends FormRequest
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
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'intended' => ['nullable', 'string', 'max:2000'],
            'invite' => ['nullable', 'string', 'max:100'],
            'early' => ['nullable', 'string', 'max:100'],
            'turnstileToken' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
