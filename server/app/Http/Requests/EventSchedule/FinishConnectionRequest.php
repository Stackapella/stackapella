<?php

declare(strict_types=1);

namespace App\Http\Requests\EventSchedule;

use Illuminate\Foundation\Http\FormRequest;

/**
 * eventSchedule.connect.finish: what Event Schedule sent back to /connect/event-schedule/callback.
 */
final class FinishConnectionRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:2048'],
            'state' => ['required', 'string', 'max:64'],
        ];
    }
}
