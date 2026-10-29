<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Gallery;

use Illuminate\Foundation\Http\FormRequest;

/**
 * admin.gallery.decide: show it, or hide it with the reason the person is told.
 */
final class DecideHeldRequest extends FormRequest
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
            'decision' => ['required', 'string', 'in:approve,hide'],
            'note' => ['required_if:decision,hide', 'nullable', 'string', 'max:1000'],
        ];
    }
}
