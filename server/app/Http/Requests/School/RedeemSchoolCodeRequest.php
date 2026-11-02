<?php

declare(strict_types=1);

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;

/**
 * school.redeem: a class code as typed or linked ("KQ7M-3XPA", any case, dash or not).
 */
final class RedeemSchoolCodeRequest extends FormRequest
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
        return ['code' => ['required', 'string', 'max:20']];
    }
}
