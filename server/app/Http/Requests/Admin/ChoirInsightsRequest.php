<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** admin.choir.show: the period, 7 or 30 days (30 by default). */
final class ChoirInsightsRequest extends FormRequest
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
        return ['period' => ['sometimes', 'nullable', 'in:7d,30d']];
    }
}
