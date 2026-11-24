<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** admin.growth.ai: the period, 28 or 90 days (28 by default). */
final class AiChoiceGrowthRequest extends FormRequest
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
        return ['period' => ['sometimes', 'nullable', 'in:28d,90d']];
    }
}
