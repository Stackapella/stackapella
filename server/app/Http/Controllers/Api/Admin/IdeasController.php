<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Ideas\Ideas;
use App\Actions\Ideas\ModerateIdeas;
use App\Exceptions\Refusal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ideas\DecideIdeaRequest;
use App\Models\Idea;
use App\Models\IdeaVoice;
use App\Models\Report;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * admin.ideas.* (Ideas): what waits for review, and Hillel's decisions.
 */
final class IdeasController extends Controller
{
    public function held(Ideas $ideas): JsonResponse
    {
        $held = Idea::query()->with(Ideas::WITH)->whereNotNull('held_at')->whereNull('hidden_at')->orderBy('held_at')->limit(100)->get();
        $counts = Ideas::readyByPart(array_values(array_map('intval', $held->pluck('id')->all())));
        $reports = Report::query()->where('reportable_type', (new Idea)->getMorphClass())->whereIn('reportable_id', $held->pluck('id'))->where('status', Report::OPEN)->get()->groupBy('reportable_id');

        return response()->json(['data' => array_values($held->map(static fn (Idea $idea): array => [
            ...$ideas->view($idea, null, $counts[$idea->id] ?? collect()),
            'writerId' => $idea->user->sqid(),
            'heldAt' => $idea->held_at?->utc()->toIso8601ZuluString(),
            'reports' => [
                'count' => ($reports->get($idea->id) ?? collect())->count(),
                'reasons' => array_values(($reports->get($idea->id) ?? collect())->pluck('reason')->unique()->sort()->values()->all()),
            ],
        ])->all())]);
    }

    public function decide(DecideIdeaRequest $request, string $idea, ModerateIdeas $moderate, Ideas $ideas, #[CurrentUser] User $admin): JsonResponse
    {
        $found = Idea::query()->where('public_id', $idea)->first() ?? throw new Refusal(404, 'not_found');
        $voiceId = $request->validated('voiceId');
        $voice = is_string($voiceId) ? (IdeaVoice::query()->where('uuid', $voiceId)->where('idea_id', $found->id)->first() ?? throw new Refusal(404, 'not_found')) : null;
        $moderate->decide($found, $voice, $admin, (string) $request->validated('decision'), (string) ($request->validated('note') ?? ''), $request->ip());

        return $this->held($ideas);
    }
}
