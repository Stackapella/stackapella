<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Gallery;

use App\Actions\Gallery\Consents;
use App\Exceptions\Refusal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gallery\DecideConsentRequest;
use App\Http\Resources\Sharing\HarmonyResource;
use App\Models\Harmony;
use App\Models\HarmonyConsent;
use App\Models\User;
use App\Support\Http\Hosts;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * Friends' consent (N1): harmonies.consent.ask, consentRequests.list and .decide.
 */
final class ConsentsController extends Controller
{
    public function ask(string $harmony, Consents $consents, #[CurrentUser] User $user): JsonResponse
    {
        $found = Harmony::query()->where('public_id', $harmony)->where('user_id', $user->id)->whereNull('deleted_at')->first() ?? throw new Refusal(404, 'not_found');

        return response()->json(['data' => (new HarmonyResource($consents->ask($user, $found)->refresh()))->resolve()]);
    }

    public function index(#[CurrentUser] User $user): JsonResponse
    {
        $asked = HarmonyConsent::query()->with(['harmony.user'])->where('user_id', $user->id)->whereHas('harmony', fn ($h) => $h->whereNull('deleted_at'))->orderByDesc('id')->get();

        return response()->json(['data' => array_values($asked->map(fn (HarmonyConsent $c): array => self::view($c))->all())]);
    }

    public function decide(DecideConsentRequest $request, HarmonyConsent $consent, Consents $consents, #[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => self::view($consents->decide($user, $consent, $request->boolean('agree'))->load('harmony.user'))]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function view(HarmonyConsent $consent): array
    {
        $harmony = $consent->harmony;

        return [
            'id' => $consent->sqid(),
            'harmony' => ['id' => $harmony?->public_id, 'title' => $harmony?->title, 'pageUrl' => $harmony !== null ? Hosts::public('h/'.$harmony->public_id) : null],
            'owner' => ['name' => $harmony !== null ? $harmony->user->name : __('coaching.deleted_account')],
            'asks' => ['gallery', 'feature'],
            'status' => $consent->status,
            'decidedAt' => $consent->decided_at?->utc()->toIso8601ZuluString(),
        ];
    }
}
