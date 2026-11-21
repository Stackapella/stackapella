<?php

declare(strict_types=1);

namespace App\Http\Requests\Groups;

use Illuminate\Foundation\Http\FormRequest;

/**
 * groups.seats.update: the new number of seats (from 10, up to a group's members).
 */
final class SeatsRequest extends FormRequest
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
        return ['seats' => ['required', 'integer', 'min:'.(int) config('billing.choir.min_seats'), 'max:'.(int) config('usage.group_members')]];
    }
}
