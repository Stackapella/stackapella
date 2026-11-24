<?php

declare(strict_types=1);

namespace App\Http\Resources\SingingTest;

use App\Models\SingingTest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * contract/schemas/singingTest.json: a test's numbers as sent and the server's own score.
 *
 * @property SingingTest $resource
 */
final class SingingTestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $test = $this->resource;
        $points = static fn (mixed $value): ?float => is_numeric($value) ? (float) $value : null;

        return [
            'id' => $test->uuid,
            'testVersion' => $test->test_version,
            'scoringVersion' => $test->scored_version,
            'takenAt' => $test->taken_at->utc()->toIso8601ZuluString(),
            'anchor' => (float) $test->anchor,
            'device' => $test->device,
            'warmUp' => $test->warm_up,
            'status' => $test->status,
            'scores' => ['match' => $points($test->match_points), 'back' => $points($test->back_points), 'hold' => $points($test->hold_points)],
            'total' => $test->total,
            'band' => $test->band,
            'match' => $test->items['match'] ?? [],
            'back' => $test->items['back'] ?? [],
            'hold' => $test->items['hold'] ?? [],
        ];
    }
}
