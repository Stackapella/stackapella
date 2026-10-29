<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use App\Models\User;
use App\Rules\AvailableHandle;
use App\Rules\NoLinks;
use App\Rules\OneLine;
use App\Support\Accounts\Age;
use App\Support\Push\AppPushes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * account.update: any of the profile's fields. No links in names, handles or bios.
 */
final class UpdateAccountRequest extends FormRequest
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
        $user = $this->user();

        return [
            'name' => ['sometimes', 'required', 'string', 'max:80', new NoLinks, new OneLine],
            'handle' => ['sometimes', 'required', 'string', new AvailableHandle($user instanceof User ? $user : null)],
            'bio' => ['sometimes', 'nullable', 'string', 'max:300', new NoLinks],
            'locale' => ['sometimes', 'required', 'string', Rule::in(['en'])],
            'timezone' => ['sometimes', 'nullable', 'string', 'timezone:all'],
            'xHandle' => ['sometimes', 'nullable', 'string', 'regex:/^[A-Za-z0-9_]{1,15}$/'],
            'blueskyHandle' => ['sometimes', 'nullable', 'string', 'max:253', 'regex:/^[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/'],
            'heardFrom' => ['sometimes', 'nullable', Rule::in(['search', 'friend', 'choir', 'reddit', 'tiktok', 'instagram', 'youtube', 'app_store', 'teacher', 'other'])],
            // The old "13 or over" is gone: when they were born is the only age answer.
            'ageConfirmed' => ['prohibited'],
            // When they were born (month and year), once (Age).
            'birthMonth' => ['sometimes', 'required_with:birthYear', 'integer', 'between:1,12'],
            'birthYear' => ['sometimes', 'required_with:birthMonth', 'integer', 'min:'.Age::EARLIEST_YEAR, 'max:'.now()->year],
            'publicProfile' => ['sometimes', 'boolean'],
            // Hide profile: shown to others as "Anonymous" (docs/hide-profile-plan.md).
            'hideProfile' => ['sometimes', 'boolean'],
            // Ideas: whether the writer hears of new voices by email (the bell either way).
            'ideaEmails' => ['sometimes', 'boolean'],
            // Push to the apps: which of the bell's kinds, and quiet hours in their time zone.
            'pushTypes' => ['sometimes', 'array:'.implode(',', AppPushes::KINDS)],
            'pushTypes.*' => ['boolean'],
            'quietHours' => ['sometimes', 'nullable', 'array:start,end', 'required_array_keys:start,end'],
            'quietHours.start' => ['required_with:quietHours', 'date_format:H:i'],
            'quietHours.end' => ['required_with:quietHours', 'date_format:H:i'],
            // The theme picker (docs/decisions.md, "themes"): either or both.
            'appearance' => ['sometimes', 'array:mode,colour,accent,customAccent,skin'],
            'appearance.mode' => ['sometimes', 'string', Rule::in(User::APPEARANCE_MODES)],
            'appearance.colour' => ['sometimes', 'nullable', 'string', Rule::in(User::APPEARANCE_COLOURS)],
            'appearance.accent' => ['sometimes', 'nullable', 'string', Rule::in(User::APPEARANCE_ACCENTS)],
            // Their own accent (#rrggbb), with accent "custom"; the app fits it to each theme.
            'appearance.customAccent' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            // Game or serious; absent leaves it as it is.
            'appearance.skin' => ['sometimes', 'string', Rule::in(User::APPEARANCE_SKINS)],
        ];
    }
}
