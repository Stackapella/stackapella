<?php

declare(strict_types=1);

namespace App\Http\Requests\OwnChoir\Gallery;

use Illuminate\Foundation\Http\FormRequest;

/**
 * admin.choirGallery.list: which of the queue.
 */
final class GalleryQueueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['status' => ['sometimes', 'string', 'in:pending,held,approved,declined,removed']];
    }
}
