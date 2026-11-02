<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * An email address (resend the verification, forgot the password).
 */
final class EmailRequest extends FormRequest
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
            // An app's own secret for its sign-up (the web's is a cookie): SignUpBrowser.
            'signupKey' => ['sometimes', 'nullable', 'string', 'min:20', 'max:128'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'turnstileToken' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
