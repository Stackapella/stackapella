<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Mudeo;

use Illuminate\Foundation\Http\FormRequest;

/**
 * admin.mudeo.mark: addresses that opted out or bounced, one or many.
 */
final class MarkMudeoRequest extends FormRequest
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
            'emails' => ['required', 'array', 'min:1', 'max:500'],
            'emails.*' => ['string', 'max:255'],
            'as' => ['required', 'string', 'in:opted_out,bounced'],
        ];
    }
}
