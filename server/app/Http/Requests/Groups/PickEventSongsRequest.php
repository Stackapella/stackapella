<?php

declare(strict_types=1);

namespace App\Http\Requests\Groups;

use Illuminate\Foundation\Http\FormRequest;

/**
 * groups.rehearsals.songs: which of the group's songs an event is for.
 */
final class PickEventSongsRequest extends FormRequest
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
            'templateIds' => ['present', 'array', 'max:20'],
            'templateIds.*' => ['string', 'max:80', 'distinct'],
        ];
    }
}
