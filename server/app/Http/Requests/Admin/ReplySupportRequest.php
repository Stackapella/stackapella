<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * admin.support.reply: Hillel's answer, and whether it closes the message.
 */
final class ReplySupportRequest extends FormRequest
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
            'body' => ['required', 'string', 'min:2', 'max:10000'],
            'close' => ['sometimes', 'boolean'],
        ];
    }
}
