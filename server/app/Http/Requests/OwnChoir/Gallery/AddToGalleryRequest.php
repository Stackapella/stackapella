<?php

declare(strict_types=1);

namespace App\Http\Requests\OwnChoir\Gallery;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ownChoir.gallery.add: the share, and the consent as the sheet showed it (its version and its words' hash).
 */
final class AddToGalleryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'harmonyId' => ['required', 'string', 'max:32'],
            'consentVersion' => ['required', 'integer', 'min:1'],
            'wordsHash' => ['required', 'string', 'regex:/^[0-9a-f]{64}$/'],
            'locale' => ['required', 'string', 'in:en,es'],
            // "Only your own voice": the sheet's claim, said by the singer.
            'ownVoice' => ['required', 'accepted'],
        ];
    }
}
