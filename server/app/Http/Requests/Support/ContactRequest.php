<?php

declare(strict_types=1);

namespace App\Http\Requests\Support;

use App\Models\SupportMessage;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * support.create: a message to us, the context the person chose to include, and the honeypot (`website`).
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
            // "Don't reply to me": no reply address kept, the account's or the one typed (while feedback2 is on).
            'reply' => ['sometimes', 'boolean'],
            // No topic chosen: kept as `other` (SupportInbox).
            'category' => ['nullable', Rule::in(SupportMessage::CATEGORIES)],
            'moment' => ['nullable', Rule::in(SupportMessage::MOMENTS)],
            'message' => ['required', 'string', 'min:2', 'max:5000'],
            'context' => ['nullable', 'array'],
            'context.appVersion' => ['nullable', 'string', 'max:40'],
            // A browser family and a device kind (DeviceDetails); an older app's user agent is reduced to them before
            // it's kept, so its length is only capped here.
            'context.browser' => ['nullable', 'string', 'max:400'],
            'context.device' => ['nullable', 'string', 'max:400'],
            'context.page' => ['nullable', 'string', 'max:300'],
            'context.language' => ['nullable', 'string', 'max:12', 'regex:/^[A-Za-z]{2,3}(-[A-Za-z0-9]{2,8})*$/'],
            'context.skin' => ['nullable', Rule::in(['game', 'serious'])],
            // Only when the moment has one: the song and part, the choir song, Sentry's code for the error showing.
            'context.templateId' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9-]+$/'],
            'context.partKey' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/'],
            'context.choirRequestId' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/'],
            'context.errorCode' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_.:-]+$/'],
            'context.choirIssue' => ['nullable', Rule::in(SupportMessage::CHOIR_ISSUES)],
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
