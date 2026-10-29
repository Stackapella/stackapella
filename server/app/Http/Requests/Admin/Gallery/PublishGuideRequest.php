<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Gallery;

use Illuminate\Foundation\Http\FormRequest;

/**
 * admin.gallery.guides.create: which of the song's parts becomes a guide voice, and whether it
 * leads that part.
 */
final class PublishGuideRequest extends FormRequest
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
            'partId' => ['required', 'uuid'],
            'makeDefault' => ['sometimes', 'boolean'],
        ];
    }
}
