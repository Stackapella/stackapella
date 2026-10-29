<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use App\Rules\StrongPassword;
use Illuminate\Foundation\Http\FormRequest;

/**
 * account.password.update.
 */
final class ChangePasswordRequest extends FormRequest
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
            'currentPassword' => ['nullable', 'string', 'max:200'],
            'password' => ['required', 'string', new StrongPassword],
        ];
    }
}
