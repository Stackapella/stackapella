<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * admin.harmonies.restore: a note for the audit log, if Hillel wants one.
 */
final class RestoreHarmonyRequest extends FormRequest
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
        return ['note' => ['nullable', 'string', 'max:500']];
    }
}
