<?php

declare(strict_types=1);

namespace App\Http\Requests\Groups;

use Illuminate\Foundation\Http\FormRequest;

/**
 * groups.seats.move: the member giving up a seat and the one taking it (their ids, as groups.show shows them).
 */
final class MoveSeatRequest extends FormRequest
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
            'from' => ['required', 'string', 'max:40'],
            'to' => ['required', 'string', 'max:40', 'different:from'],
        ];
    }
}
