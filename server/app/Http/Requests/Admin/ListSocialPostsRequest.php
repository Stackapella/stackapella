<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * admin.social.list: which posts, and the next page.
 */
final class ListSocialPostsRequest extends FormRequest
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
            'status' => ['nullable', 'in:awaiting,queued,posting,posted,failed,cancelled,all'],
            'cursor' => ['nullable', 'string', 'max:512'],
        ];
    }
}
