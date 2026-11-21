<?php

declare(strict_types=1);

namespace App\Http\Requests\Worker;

use Illuminate\Foundation\Http\FormRequest;

/**
 * worker.choir.status: the worker paused at its own spending ceiling, or free again.
 */
final class ChoirStatusRequest extends FormRequest
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
            'paused' => ['present', 'nullable', 'in:daily_ceiling,monthly_ceiling'],
            'spentTodayUsd' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'spentMonthUsd' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'limitDayUsd' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'limitMonthUsd' => ['required', 'numeric', 'min:0', 'max:1000000'],
        ];
    }
}
