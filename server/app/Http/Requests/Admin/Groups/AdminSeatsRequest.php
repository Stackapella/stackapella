<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Groups;

use Illuminate\Foundation\Http\FormRequest;

/**
 * admin.groups.seats.update: move a seat, or fill the seats again from Stripe, always with a reason.
 */
final class AdminSeatsRequest extends FormRequest
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
            'action' => ['required', 'string', 'in:move,sync'],
            'from' => ['required_if:action,move', 'nullable', 'string', 'max:32'],
            'to' => ['required_if:action,move', 'nullable', 'string', 'max:32'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
