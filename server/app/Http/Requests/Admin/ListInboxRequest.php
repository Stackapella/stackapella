<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\SupportMessage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * admin.support.list and admin.appeals.list: which ones, and the next page.
 */
final class ListInboxRequest extends FormRequest
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
            'status' => ['nullable', 'string', 'max:20'],
            // The support inbox's one kind of message (tracks: directors' learning tracks requests).
            'category' => ['nullable', Rule::in([...SupportMessage::CATEGORIES, SupportMessage::TRACKS])],
            'cursor' => ['nullable', 'string', 'max:512'],
        ];
    }
}
