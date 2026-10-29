<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Support\Trust\Limits;
use Illuminate\Foundation\Http\FormRequest;

/**
 * admin.limits.update: a new maximum (or null for the default), and why.
 */
final class UpdateLimitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * An unknown limiter or rule is a 404, before anything is validated.
     */
    protected function prepareForValidation(): void
    {
        abort_unless(isset(app(Limits::class)->all()[(string) $this->route('limiter')][(string) $this->route('rule')]), 404);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'max' => ['present', 'nullable', 'integer', 'min:1', 'max:100000'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
