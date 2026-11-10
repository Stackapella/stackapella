<?php

declare(strict_types=1);

namespace App\Http\Requests\Groups;

use App\Rules\NoLinks;
use App\Rules\OneLine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * groups.update: a new name, the next rehearsal (a plain date from yesterday to a year ahead, and
 * whether it's weekly; null clears it), or both.
 */
final class UpdateGroupRequest extends FormRequest
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
        $today = now()->utc()->startOfDay();

        return [
            'name' => ['sometimes', 'string', 'min:1', 'max:80', new NoLinks, new OneLine],
            'rehearsal' => ['nullable', 'array:date,weekly'],
            'rehearsal.date' => ['required_with:rehearsal', 'date_format:Y-m-d', 'after_or_equal:'.$today->subDay()->toDateString(), 'before_or_equal:'.$today->addYear()->toDateString()],
            'rehearsal.weekly' => ['required_with:rehearsal', 'boolean'],
        ];
    }

    /**
     * @return list<\Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->has('name') && ! $this->has('rehearsal')) {
                $validator->errors()->add('name', __('validation.required', ['attribute' => 'name']));
            }
        }];
    }
}
