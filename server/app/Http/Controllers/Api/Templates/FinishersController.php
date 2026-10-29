<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Templates;

use App\Exceptions\Refusal;
use App\Http\Controllers\Controller;
use App\Models\Template;
use App\Models\TemplateStat;
use Illuminate\Http\JsonResponse;

/**
 * templates.finishers: What's next's numbers for an official song (counted nightly): how many have
 * finished it, and what they also learned, once there are enough finishers to mean something.
 */
final class FinishersController extends Controller
{
    public function __invoke(string $template): JsonResponse
    {
        $song = Template::query()->listed()->where('slug', $template)->first() ?? throw new Refusal(404, 'not_found');
        $stat = TemplateStat::query()->where('template_id', $song->id)->first();
        $shown = $stat !== null && $stat->finishers >= (int) config('templates.finishers_to_show');
        $also = $shown ? Template::query()->listed()->whereIn('id', $stat->also_learned)->get(['id', 'slug', 'title'])->sortBy(fn (Template $t): int|false => array_search($t->id, $stat->also_learned, true))->values() : collect();

        return response()->json(['data' => [
            'finishers' => $shown ? $stat->finishers : null,
            'alsoLearned' => $also->map(fn (Template $t): array => ['id' => $t->slug, 'title' => $t->title])->all(),
            'countedAt' => $stat?->computed_at->utc()->toIso8601ZuluString(),
        ]]);
    }
}
