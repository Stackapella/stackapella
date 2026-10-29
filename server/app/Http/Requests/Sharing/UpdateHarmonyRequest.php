<?php

declare(strict_types=1);

namespace App\Http\Requests\Sharing;

use App\Models\Harmony;
use App\Rules\NoLinks;
use Illuminate\Foundation\Http\FormRequest;

/**
 * harmonies.update: a new title, or who can hear it.
 */
final class UpdateHarmonyRequest extends FormRequest
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
            'title' => ['sometimes', 'nullable', 'string', 'max:120', new NoLinks],
            'visibility' => ['sometimes', 'string', 'in:'.Harmony::PRIVATE.','.Harmony::UNLISTED],
            'mayFeature' => ['sometimes', 'boolean'],
            'inGallery' => ['sometimes', 'boolean'],
            // The gallery (N1): "Do you have the right to share this song?", and comments off.
            'rightsConfirmed' => ['sometimes', 'boolean'],
            'commentsOff' => ['sometimes', 'boolean'],
            // Open parts (N5): the fill featured on its page, or none.
            'featuredFillId' => ['sometimes', 'nullable', 'string', 'size:12'],
        ];
    }
}
