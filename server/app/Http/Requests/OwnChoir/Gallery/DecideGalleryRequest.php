<?php

declare(strict_types=1);

namespace App\Http\Requests\OwnChoir\Gallery;

use App\Models\ChoirGalleryItem;
use Illuminate\Foundation\Http\FormRequest;

/**
 * admin.choirGallery.decide: a reason with a decline or a takedown, staff's own sentence only with `other`.
 */
final class DecideGalleryRequest extends FormRequest
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
        return [
            'decision' => ['required', 'string', 'in:approve,decline,takedown,release'],
            'reason' => ['required_if:decision,decline,takedown', 'nullable', 'string', 'in:'.implode(',', ChoirGalleryItem::REASONS)],
            'reasonText' => ['required_if:reason,other', 'nullable', 'string', 'max:500'],
        ];
    }
}
