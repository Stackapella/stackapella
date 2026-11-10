<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Outreach;

use App\Models\OutreachTarget;
use Closure;

/**
 * Rules the outreach requests share: an organization's fields, and a contact that's an address or
 * a form's web address, published on a page we keep beside it.
 */
final class OutreachRules
{
    /**
     * @return array<string, list<mixed>>
     */
    public static function target(bool $partial): array
    {
        $need = $partial ? 'sometimes' : 'required';

        return [
            'name' => [$need, 'string', 'max:160'],
            'group' => [$need, 'string', 'max:40'],
            'country' => ['sometimes', 'nullable', 'string', 'size:2'],
            'reach' => ['sometimes', 'nullable', 'string', 'max:80'],
            'fit' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'contact' => ['sometimes', 'nullable', 'string', 'max:500', self::contact()],
            'source' => ['sometimes', 'nullable', 'url:https,http', 'max:500', 'required_with:contact'],
            'angle' => [$need, 'string', 'in:'.implode(',', OutreachTarget::ANGLES)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            // Time-sensitive emails: send by (the wording goes stale after), and a follow-up's earliest day.
            'sendBy' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'followUpNotBefore' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'sendNotBefore' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'timingNote' => ['sometimes', 'nullable', 'string', 'max:1000'],
            // What was found about it (marketing's research), each specific with its page.
            'research' => ['sometimes', 'nullable', 'array'],
            'research.note' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'research.links' => ['sometimes', 'array', 'max:10'],
            'research.links.*' => ['url:https,http', 'max:500'],
        ];
    }

    /**
     * An email address, or an http(s) address of a contact form.
     */
    public static function contact(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            $value = trim((string) $value);

            if ($value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false && ! (filter_var($value, FILTER_VALIDATE_URL) !== false && preg_match('#^https?://#i', $value) === 1)) {
                $fail(__('outreach.import.bad_contact'));
            }
        };
    }
}
