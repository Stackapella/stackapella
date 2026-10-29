<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Flag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One switch or dark feature (contract/schemas/admin.flag.json): its definition from
 * config/flags.php, with the stored row (if it was ever changed).
 *
 * @property array{name: string, definition: array{kind: string, values: list<string>}, value: string, row: Flag|null} $resource
 */
final class FlagResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $row = $this->resource['row'];

        return [
            'name' => $this->resource['name'],
            'kind' => $this->resource['definition']['kind'],
            'value' => $this->resource['value'],
            'values' => $this->resource['definition']['values'],
            'reason' => $row?->reason,
            'changedAt' => $row?->updated_at?->utc()->toIso8601ZuluString(),
            'changedBy' => PersonResource::of($row?->updater),
        ];
    }
}
