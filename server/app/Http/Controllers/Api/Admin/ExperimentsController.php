<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Experiments\ManageExperiments;
use App\Http\Controllers\Controller;
use App\Http\Requests\Experiments\ExperimentRequest;
use App\Models\Experiment;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * Admin > Experiments (docs/experiments-plan.md): the registry, newest first, and its changes (in
 * sudo, audited).
 */
final class ExperimentsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Experiment::query()->orderByDesc('id')->get()->map(self::view(...))->values()->all()]);
    }

    public function store(ExperimentRequest $request, ManageExperiments $experiments, #[CurrentUser] User $admin): JsonResponse
    {
        return response()->json(['data' => self::view($experiments->create($admin, ['id' => $request->validated('id'), ...$request->fields()], $request->ip()))], 201);
    }

    public function update(ExperimentRequest $request, string $experiment, ManageExperiments $experiments, #[CurrentUser] User $admin): JsonResponse
    {
        $action = $request->validated('action');
        $winner = $request->validated('winner');

        return response()->json(['data' => self::view($experiments->update($admin, $experiment, $request->fields(), is_string($action) ? $action : null, is_string($winner) ? $winner : null, $request->ip()))]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function view(Experiment $experiment): array
    {
        return [
            'id' => $experiment->key,
            'what' => $experiment->what,
            'hypothesis' => $experiment->hypothesis,
            'arms' => array_values($experiment->arms),
            'metric' => $experiment->metric,
            'guardrails' => array_values($experiment->guardrails),
            'audience' => (object) $experiment->audience,
            'excludes' => array_values($experiment->excludes),
            'plannedPerArm' => $experiment->planned_per_arm,
            'startsOn' => $experiment->starts_on?->toDateString(),
            'endsOn' => $experiment->ends_on?->toDateString(),
            'readFrom' => $experiment->readFrom(),
            'status' => $experiment->status(),
            'winner' => $experiment->winner,
            'createdAt' => $experiment->created_at?->utc()->toIso8601ZuluString(),
        ];
    }
}
