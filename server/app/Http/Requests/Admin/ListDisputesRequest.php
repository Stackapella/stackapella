<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Rules\ValidCursor;
use Illuminate\Foundation\Http\FormRequest;

/**
 * admin.billing.disputes.list's cursor.
 */
final class ListDisputesRequest extends FormRequest
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
            'cursor' => ['nullable', 'string', 'max:500', new ValidCursor],
        ];
    }
}
