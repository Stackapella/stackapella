<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Voices;

use App\Actions\Voices\LendVoice;
use App\Actions\Voices\ShareVoice;
use App\Enums\CreditMode;
use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Voices\ShareVoiceRequest;
use App\Http\Resources\Beta\BetaMeResource;
use App\Models\BetaAssignment;
use App\Models\User;
use App\Support\Beta\VoiceCount;
use App\Support\Ids;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Lend your voice (docs/lend-your-voice-plan.md): the Wanted voices, joining as a volunteer, and
 * claims. Recording a claim is the beta singers' (beta.parts.list, beta.submissions.*).
 */
final class VoicesController extends Controller
{
    public function __construct(private readonly LendVoice $voices) {}

    public function summary(): JsonResponse
    {
        return response()->json(['data' => $this->voices->summary()]);
    }

    public function wanted(Request $request): JsonResponse
    {
        $request->validate(['kind' => ['sometimes', 'in:songs,clips'], 'skill' => ['sometimes', 'string', 'max:24']]);
        $kind = $request->query('kind');
        $skill = $request->query('skill');

        return response()->json(['data' => $this->voices->wanted(is_string($kind) ? $kind : null, is_string($skill) ? $skill : null)]);
    }

    public function join(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $request->validate(['agreementVersion' => ['required', 'integer'], 'credit' => ['required', 'in:name,anonymous'], 'linksProfile' => ['sometimes', 'boolean']]);
        $singer = $this->voices->join($user, (int) $request->input('agreementVersion'), CreditMode::from((string) $request->input('credit')), $request->boolean('linksProfile'));

        return (new BetaMeResource($singer->load('user'), VoiceCount::of($singer)))->response()->setStatusCode(200);
    }

    /** voices.share: a kept take offered as a part's guide voice (docs/open-guide-voices-plan.md). */
    public function share(ShareVoiceRequest $request, ShareVoice $share, #[CurrentUser] User $user): JsonResponse
    {
        $submission = $share->handle($user, [
            'takeId' => (string) $request->validated('takeId'),
            'templateId' => (string) $request->validated('templateId'),
            'templateVersion' => (int) $request->validated('templateVersion'),
            'partKey' => (string) $request->validated('partKey'),
            'credit' => (string) $request->validated('credit'),
            'linksProfile' => $request->boolean('linksProfile'),
            'agreementVersion' => (int) $request->validated('agreementVersion'),
            'adult' => $request->boolean('adult'),
        ]);

        return response()->json(['data' => [
            'id' => $submission->sqid(),
            'status' => $submission->status === SubmissionStatus::Held ? 'held' : 'sent',
            'templateId' => (string) $request->validated('templateId'),
            'partKey' => (string) $submission->part_key,
            'sharedAt' => $submission->created_at?->utc()->toIso8601ZuluString(),
        ]]);
    }

    public function claims(#[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => array_map(self::claimView(...), $this->voices->claims($user))]);
    }

    public function claim(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $request->validate(['item' => ['required', 'string', 'max:140']]);
        $claim = $this->voices->claim($user, (string) $request->input('item'))->load(['template', 'clip', 'latestSubmission']);

        return response()->json(['data' => self::claimView($claim)], 201);
    }

    public function release(string $claim, #[CurrentUser] User $user): Response
    {
        $this->voices->release($user, Ids::decode($claim) ?? 0);

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private static function claimView(BetaAssignment $claim): array
    {
        $ending = $claim->claimed_at !== null && now()->gte($claim->claimed_at->copy()->addDays((int) config('beta.claim_remind_after_days')));

        return [
            'id' => $claim->sqid(),
            'item' => $claim->clip !== null ? 'clip:'.$claim->clip->slot : 'song:'.$claim->template?->slug.':'.$claim->part_key,
            'title' => $claim->clip !== null ? (string) ($claim->clip->brief['sing'][app()->getLocale()] ?? $claim->clip->brief['sing']['en'] ?? '') : (string) $claim->template?->title,
            'partKey' => $claim->part_key,
            'claimedAt' => $claim->claimed_at?->utc()->toIso8601ZuluString(),
            'expiresAt' => $claim->expires_at?->utc()->toIso8601ZuluString(),
            // From day 10, the app says it's ending (in the app only: no email).
            'ending' => $ending,
            'take' => $claim->latestSubmission?->status->value,
        ];
    }
}
