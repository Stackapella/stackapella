<?php

declare(strict_types=1);

namespace App\Http\Requests\Support;

use App\Models\SupportMessage;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * support.create: a message to Hillel, the context the person chose to include, and the honeypot (`website`).
 */
final class ContactRequest extends FormRequest
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
            'email' => ['nullable', 'email:rfc', 'max:254'],
            'category' => ['required', Rule::in(SupportMessage::CATEGORIES)],
            'message' => ['required', 'string', 'min:2', 'max:5000'],
            'context' => ['nullable', 'array'],
            'context.appVersion' => ['nullable', 'string', 'max:40'],
            'context.browser' => ['nullable', 'string', 'max:200'],
            'context.device' => ['nullable', 'string', 'max:200'],
            'context.page' => ['nullable', 'string', 'max:300'],
            // The app's last timing check, as it keeps it: kept small (a few numbers and names).
            'context.timingCheck' => ['nullable', 'array', 'max:30', function (string $attribute, mixed $value, Closure $fail): void {
                if (strlen((string) json_encode($value)) > 4000) {
                    $fail(__('validation.max.string', ['attribute' => $attribute, 'max' => 4000]));
                }
            }],
            'context.audioErrors' => ['nullable', 'array', 'max:20'],
            'context.audioErrors.*' => ['string', 'max:500'],
            // The honeypot: anything in it is a bot, answered as any message is (never a 422).
            'website' => ['nullable'],
        ];
    }
}
