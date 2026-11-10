<?php

declare(strict_types=1);

namespace App\Http\Requests\Groups;

use App\Actions\Groups\RequestTracks;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * groups.tracks.create (docs/choir-tracks-plan.md, stage 1): the piece a director would like learning tracks of, and
 * their word on its rights. The score itself comes by email, never here.
 */
final class TracksRequest extends FormRequest
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
            'piece' => ['required', 'string', 'min:1', 'max:200'],
            'composer' => ['nullable', 'string', 'max:200'],
            'voicing' => ['nullable', 'string', 'max:60'],
            'rights' => ['required', Rule::in(RequestTracks::RIGHTS)],
            'rightsConfirmed' => ['accepted'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
