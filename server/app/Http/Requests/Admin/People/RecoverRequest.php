<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\People;

use Illuminate\Foundation\Http\FormRequest;

/**
 * admin.people.recover: the address the person can read now, and how support checked it's them.
 */
final class RecoverRequest extends FormRequest
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
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
