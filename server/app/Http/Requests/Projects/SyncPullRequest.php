<?php

declare(strict_types=1);

namespace App\Http\Requests\Projects;

use App\Support\Sync\Changes;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * sync.pull's cursor, as the last pull gave it.
 */
final class SyncPullRequest extends FormRequest
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
            // `8` was how a client asked for peaks8; every answer has them now, so it's only accepted.
            'peaks' => ['sometimes', 'string', 'in:8'],
            'cursor' => ['nullable', 'string', 'max:40', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || Changes::decode($value) === null) {
                    $fail(__('validation.cursor'));
                }
            }],
        ];
    }
}
