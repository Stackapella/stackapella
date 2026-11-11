<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Give a partner's song to an account: the template JSON the arranger prepared, and the rights
 * record from the partner's written confirmation (docs/partner-songs.md).
 */
final class GiveSongRequest extends FormRequest
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
            'song' => ['required', 'array'],
            'rights' => ['required', 'array'],
            'rights.confirmedBy' => ['required', 'string', 'max:160'],
            'rights.confirmedAt' => ['required', 'date', 'before_or_equal:now'],
            'rights.wording' => ['required', 'string', 'max:2000'],
            'rights.source' => ['required', 'string', 'max:500'],
            // A director's learning tracks request this delivers (TrackRequests::deliver).
            'trackRequest' => ['nullable', 'string', 'max:40'],
        ];
    }
}
