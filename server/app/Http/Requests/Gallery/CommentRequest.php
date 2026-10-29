<?php

declare(strict_types=1);

namespace App\Http\Requests\Gallery;

use App\Models\Comment;
use Illuminate\Foundation\Http\FormRequest;

/**
 * comments.create: plain text, 1 to 500 characters (links are refused by the action).
 */
final class CommentRequest extends FormRequest
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
            'body' => ['required', 'string', 'min:1', 'max:'.Comment::MAX],
        ];
    }
}
