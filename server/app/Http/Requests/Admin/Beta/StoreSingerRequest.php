<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Beta;

use App\Rules\TemplatePart;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * admin.beta.singers.create: the singer, their rate, and the parts they're asked to record.
 */
final class StoreSingerRequest extends FormRequest
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
        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
            'expiresAt' => ['nullable', 'date', 'after:now'],
            'hourlyRateCents' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'currency' => ['nullable', 'string', 'size:3', 'alpha'],
            'parts' => ['present', 'array', 'max:100'],
            'parts.*.templateId' => ['required', 'string', 'exists:templates,slug'],
            'parts.*.minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
        ];

        foreach (array_keys((array) $this->input('parts')) as $i) {
            $rules["parts.{$i}.partKey"] = ['required', 'string', new TemplatePart($this->input("parts.{$i}.templateId"))];
        }

        return $rules;
    }

    /**
     * The same part of the same song twice is one part.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $seen = [];

                foreach ((array) $this->input('parts') as $i => $part) {
                    $pair = (is_array($part) ? ($part['templateId'] ?? '') : '').':'.(is_array($part) ? ($part['partKey'] ?? '') : '');

                    if (isset($seen[$pair])) {
                        $validator->errors()->add("parts.{$i}.partKey", __('validation.part_twice'));
                    }

                    $seen[$pair] = true;
                }
            },
        ];
    }
}
