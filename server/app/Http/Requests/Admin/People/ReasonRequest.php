<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\People;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Suspending or unsuspending someone: why, for the audit log.
 */
final class ReasonRequest extends FormRequest
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
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
