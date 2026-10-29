<?php

declare(strict_types=1);

namespace App\Http\Requests\EventSchedule;

use Illuminate\Foundation\Http\FormRequest;

/**
 * eventSchedule.connection.update and groups.eventSchedule.update: one of the person's ES schedules.
 */
final class PickScheduleRequest extends FormRequest
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
            'schedule' => ['required', 'string', 'max:64'],
        ];
    }
}
