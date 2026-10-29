<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

/**
 * account.avatar.update: an image up to 2 MB.
 */
final class AvatarRequest extends FormRequest
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
            // The size in pixels too: a small file can still decode to a huge image.
            'avatar' => ['required', 'file', 'image', 'mimes:jpeg,png,webp', 'max:2048', 'dimensions:max_width=3000,max_height=3000'],
        ];
    }
}
