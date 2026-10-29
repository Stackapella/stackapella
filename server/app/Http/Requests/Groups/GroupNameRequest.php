<?php

declare(strict_types=1);

namespace App\Http\Requests\Groups;

use App\Rules\NoLinks;
use App\Rules\OneLine;
use Illuminate\Foundation\Http\FormRequest;

/**
 * groups.create and groups.update: the group's name.
 */
final class GroupNameRequest extends FormRequest
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
        return ['name' => ['required', 'string', 'min:1', 'max:80', new NoLinks, new OneLine], 'adult' => ['nullable', 'boolean']];
    }
}
