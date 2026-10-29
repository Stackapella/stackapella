<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\BlockType;
use App\Rules\ValidCursor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * admin.blocklist.list's filters.
 */
final class ListBlocksRequest extends FormRequest
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
            'type' => ['nullable', Rule::enum(BlockType::class)],
            'cursor' => ['nullable', 'string', 'max:500', new ValidCursor],
        ];
    }
}
