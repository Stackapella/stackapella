<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Billing;

use App\Exceptions\Refusal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\ListScoresRequest;
use App\Http\Requests\Billing\SaveScoreRequest;
use App\Models\Score;
use App\Models\User;
use App\Support\Usage\Plans;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * scores.create and scores.list: the score history (numbers only). Everyone's is kept; the list
 * is Plus's.
 */
final class ScoresController extends Controller
{
    public function store(SaveScoreRequest $request, #[CurrentUser] User $user): JsonResponse
    {
        /** @var array{id: string, lesson?: string|null, templateId?: string|null, templateVersion?: int|null, partKey?: string|null, recordedAt: string, summary: array<string, mixed>} $data */
        $data = $request->validated();
        $existing = Score::query()->where('uuid', $data['id'])->first();

        if ($existing !== null) {
            $same = $existing->user_id === $user->id && $existing->summary == $data['summary'] && $existing->lesson === ($data['lesson'] ?? null);

            if (! $same) {
                throw ValidationException::withMessages(['id' => __('validation.score_id')]);
            }

            return response()->json(['data' => self::fields($existing)]);
        }

        $score = new Score([
            'lesson' => $data['lesson'] ?? null,
            'template_slug' => $data['templateId'] ?? null,
            'template_version' => $data['templateVersion'] ?? null,
            'part_key' => $data['partKey'] ?? null,
            'recorded_at' => Carbon::parse($data['recordedAt'])->utc(),
            'summary' => $data['summary'],
        ]);
        $score->forceFill(['uuid' => $data['id'], 'user_id' => $user->id])->save();

        return response()->json(['data' => self::fields($score)], 201);
    }

    public function index(ListScoresRequest $request, Plans $plans, #[CurrentUser] User $user): JsonResponse
    {
        if (! $plans->isPlus($user)) {
            throw new Refusal(403, 'plus_required');
        }

        $since = $request->validated('since');
        $page = Score::query()
            ->where('user_id', $user->id)
            ->when($request->validated('templateId'), fn ($query, $slug) => $query->where('template_slug', $slug))
            ->when($request->validated('partKey'), fn ($query, $part) => $query->where('part_key', $part))
            ->when($request->validated('lesson'), fn ($query, $lesson) => $query->where('lesson', $lesson))
            ->when(is_string($since), fn ($query) => $query->where('created_at', '>', Carbon::parse((string) $since)))
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->cursorPaginate(100);

        return response()->json([
            'data' => collect($page->items())->map(fn (Score $score): array => self::fields($score))->all(),
            'meta' => ['nextCursor' => $page->nextCursor()?->encode()],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function fields(Score $score): array
    {
        return [
            'id' => $score->uuid,
            'takeId' => $score->take_uuid,
            'templateId' => $score->template_slug,
            'templateVersion' => $score->template_version,
            'partKey' => $score->part_key,
            'lesson' => $score->lesson,
            'recordedAt' => $score->recorded_at->utc()->toIso8601ZuluString(),
            'summary' => (object) $score->summary,
        ];
    }
}
