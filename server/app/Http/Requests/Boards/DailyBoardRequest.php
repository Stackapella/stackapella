<?php

declare(strict_types=1);

namespace App\Http\Requests\Boards;

use App\Support\Boards\Daily;
use Illuminate\Foundation\Http\FormRequest;

/**
 * boards.daily.show: a day (today by default, UTC) and a scope.
 */
final class DailyBoardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date_format:Y-m-d'],
            'scope' => ['nullable', 'in:global,friends'],
        ];
    }

    public function day(): string
    {
        $date = $this->validated('date');

        return is_string($date) ? $date : Daily::today();
    }
}
