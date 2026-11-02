<?php

declare(strict_types=1);

namespace App\Http\Requests\School;

use App\Actions\School\SchoolCodes;
use Illuminate\Foundation\Http\FormRequest;

/**
 * school.classes.create and .update (docs/school-offer-plan.md): a class code's name and seats (both
 * on create, either on update) and, on update, `action: close`.
 */
final class SchoolClassRequest extends FormRequest
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
        $need = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$need, 'string', 'min:1', 'max:80'],
            'seats' => [$need, 'integer', 'min:1', 'max:'.SchoolCodes::MAX_SEATS],
            'action' => ['sometimes', 'in:close'],
        ];
    }
}
