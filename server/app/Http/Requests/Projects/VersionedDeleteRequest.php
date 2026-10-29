<?php

declare(strict_types=1);

namespace App\Http\Requests\Projects;

use Illuminate\Foundation\Http\FormRequest;

/**
 * projects.delete and parts.delete: the version it was based on, and the outbox entry's id.
 */
final class VersionedDeleteRequest extends FormRequest
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
            'version' => ['required', 'integer', 'min:1'],
            'changeId' => ['required', 'uuid'],
        ];
    }
}
