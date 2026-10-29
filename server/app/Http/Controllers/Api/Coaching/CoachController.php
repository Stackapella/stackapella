<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coaching;

use App\Actions\Coaching\Applications;
use App\Actions\Coaching\CoachSetup;
use App\Http\Controllers\Controller;
use App\Http\Requests\Coaching\ApplyRequest;
use App\Http\Requests\Coaching\OfferingRequest;
use App\Http\Requests\Coaching\PackRequest;
use App\Http\Requests\Coaching\ProfileRequest;
use App\Models\CoachApplication;
use App\Models\CoachOffering;
use App\Models\ExercisePack;
use App\Models\User;
use App\Support\Coaching\CoachingView;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * coach.*: becoming a coach and a coach's own setup (contract/api.md, "Coaching").
 */
final class CoachController extends Controller
{
    public function __construct(private readonly CoachSetup $setup) {}

    public function application(#[CurrentUser] User $user): JsonResponse
    {
        return $this->applicationResponse(CoachApplication::query()->where('user_id', $user->id)->latest('id')->first());
    }

    public function apply(ApplyRequest $request, Applications $applications, #[CurrentUser] User $user): JsonResponse
    {
        /** @var array{experience: string, training: string, voiceTypes: list<string>, styles: list<string>, languages: list<string>, timezone: string, introUrl?: string|null, sampleReview: array<string, mixed>} $data */
        $data = $request->validated();

        return $this->applicationResponse($applications->apply($user, $data), 201);
    }

    public function profile(#[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => CoachingView::profile($this->setup->profile($user))]);
    }

    public function updateProfile(ProfileRequest $request, #[CurrentUser] User $user): JsonResponse
    {
        /** @var array{bio?: string, voiceTypes?: list<string>, styles?: list<string>, languages?: list<string>, timezone?: string, cancellationHours?: int} $data */
        $data = $request->validated();

        return response()->json(['data' => CoachingView::profile($this->setup->updateProfile($user, $data))]);
    }

    public function connect(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $request->validate(['country' => ['nullable', 'string', 'regex:/^[A-Za-z]{2}$/']]);
        $country = $request->input('country');

        return response()->json(['data' => ['url' => $this->setup->onboarding($user, is_string($country) ? $country : null)]]);
    }

    public function connectStatus(#[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => $this->setup->connectStatus($user)]);
    }

    public function offerings(#[CurrentUser] User $user): JsonResponse
    {
        $this->setup->profile($user);

        return response()->json(['data' => CoachOffering::query()->where('coach_id', $user->id)->orderBy('id')->get()->map(fn (CoachOffering $o): array => CoachingView::offering($o))->all()]);
    }

    public function createOffering(OfferingRequest $request, #[CurrentUser] User $user): JsonResponse
    {
        /** @var array{kind?: string, title?: string, description?: string, priceCents?: int, currency?: string, turnaroundDays?: int, maxSeconds?: int|null, reviewsPerMonth?: int|null, archived?: bool} $data */
        $data = $request->validated();

        return response()->json(['data' => CoachingView::offering($this->setup->saveOffering($user, null, $data))], 201);
    }

    public function updateOffering(OfferingRequest $request, string $offering, #[CurrentUser] User $user): JsonResponse
    {
        /** @var array{kind?: string, title?: string, description?: string, priceCents?: int, currency?: string, turnaroundDays?: int, maxSeconds?: int|null, reviewsPerMonth?: int|null, archived?: bool} $data */
        $data = $request->validated();

        return response()->json(['data' => CoachingView::offering($this->setup->saveOffering($user, $offering, $data))]);
    }

    public function packs(#[CurrentUser] User $user): JsonResponse
    {
        $this->setup->profile($user);

        return response()->json(['data' => ExercisePack::query()->with('coach')->where('coach_id', $user->id)->orderBy('id')->get()->map(fn (ExercisePack $p): array => CoachingView::pack($p))->all()]);
    }

    public function savePack(PackRequest $request, string $pack, #[CurrentUser] User $user): JsonResponse
    {
        /** @var array{title: string, description: string, priceCents: int, currency: string, exercises: list<array<string, mixed>>} $data */
        $data = $request->validated();

        return response()->json(['data' => CoachingView::pack($this->setup->savePack($user, $pack, $data)->load('coach'))]);
    }

    public function submitPack(string $pack, #[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => CoachingView::pack($this->setup->submitPack($user, $pack)->load('coach'))]);
    }

    private function applicationResponse(?CoachApplication $application, int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => $application !== null ? self::applicationFields($application) : null,
            'sampleTakeUrl' => Storage::disk('media')->url((string) config('coaching.sample_take_key')),
            'agreementVersion' => (int) config('coaching.agreement_version'),
        ], $status);
    }

    /**
     * @return array<string, mixed>
     */
    public static function applicationFields(CoachApplication $application): array
    {
        return [
            'id' => $application->sqid(),
            'status' => $application->status,
            'answers' => $application->answers,
            'sampleReview' => $application->sample_review,
            'note' => $application->note,
            'canApplyAgainAt' => $application->status === CoachApplication::DECLINED ? $application->decided_at?->copy()->addDays((int) config('coaching.reapply_days'))->utc()->toIso8601ZuluString() : null,
            'createdAt' => $application->created_at?->utc()->toIso8601ZuluString(),
            'decidedAt' => $application->decided_at?->utc()->toIso8601ZuluString(),
        ];
    }
}
