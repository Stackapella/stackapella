<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Outreach;

use Illuminate\Foundation\Http\FormRequest;

final class StoreOutreachTargetRequest extends FormRequest
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
        return OutreachRules::target(partial: false);
    }
}
