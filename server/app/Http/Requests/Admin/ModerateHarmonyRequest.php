<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Harmony;
use Illuminate\Foundation\Http\FormRequest;

/**
 * admin.harmonies.hide and .remove: a copyright notice or Hillel's call, with a note for the audit
 * log.
 */
final class ModerateHarmonyRequest extends FormRequest
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
            'reason' => ['required', 'string', 'in:'.Harmony::HIDDEN_DMCA.','.Harmony::HIDDEN_ADMIN],
            'note' => ['required', 'string', 'min:1', 'max:500'],
        ];
    }
}
