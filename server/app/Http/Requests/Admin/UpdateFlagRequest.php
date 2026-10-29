<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Support\Flags;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * admin.flags.update: one of the flag's values, and why.
 */
final class UpdateFlagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * An unknown flag is a 404, before anything is validated.
     */
    protected function prepareForValidation(): void
    {
        abort_unless(app(Flags::class)->exists((string) $this->route('name')), 404);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $values = app(Flags::class)->definition((string) $this->route('name'))['values'];

        return [
            'value' => ['required', 'string', Rule::in($values)],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
