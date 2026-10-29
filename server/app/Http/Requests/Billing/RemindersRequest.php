<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;

/**
 * reminders.update: the days, and a local time on a quarter hour (the job runs every 15 minutes).
 */
final class RemindersRequest extends FormRequest
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
            'enabled' => ['required', 'boolean'],
            'days' => ['present', 'array', 'max:7'],
            'days.*' => ['integer', 'between:0,6'],
            'time' => ['required_if:enabled,true', 'nullable', 'string', 'regex:/^([01]\d|2[0-3]):(00|15|30|45)$/'],
            'timezone' => ['required_if:enabled,true', 'nullable', 'string', 'timezone:all'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['time.regex' => __('validation.reminder_time')];
    }
}
