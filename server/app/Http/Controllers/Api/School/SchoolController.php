<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\School;

use App\Actions\School\SchoolCodes;
use App\Http\Controllers\Controller;
use App\Http\Requests\School\RedeemSchoolCodeRequest;
use App\Http\Requests\School\SchoolClassRequest;
use App\Models\SchoolClass;
use App\Models\User;
use App\Support\Billing\BillingState;
use App\Support\Http\Hosts;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * The school offer (docs/school-offer-plan.md): a teacher's class codes, and redeeming one.
 */
final class SchoolController extends Controller
{
    public function __construct(private readonly SchoolCodes $codes) {}

    public function index(#[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => $this->codes->mine($user)->map(self::view(...))->values()]);
    }

    public function store(SchoolClassRequest $request, #[CurrentUser] User $user): JsonResponse
    {
        $class = $this->codes->create($user, (string) $request->validated('name'), (int) $request->validated('seats'));

        return response()->json(['data' => self::view($class)], 201);
    }

    public function update(SchoolClassRequest $request, SchoolClass $class, #[CurrentUser] User $user): JsonResponse
    {
        $name = $request->validated('name');
        $seats = $request->validated('seats');
        $class = $this->codes->update($user, $class, is_string($name) ? $name : null, $seats !== null ? (int) $seats : null, $request->validated('action') === 'close');

        return response()->json(['data' => self::view($class)]);
    }

    public function redeem(RedeemSchoolCodeRequest $request, #[CurrentUser] User $user, BillingState $billing): JsonResponse
    {
        $this->codes->redeem($user, (string) $request->validated('code'));

        return response()->json(['data' => $billing->show($user->refresh())]);
    }

    /**
     * What a teacher sees of a code: never who used it.
     *
     * @return array<string, mixed>
     */
    public static function view(SchoolClass $class): array
    {
        return [
            'id' => $class->sqid(),
            'name' => $class->name,
            'code' => $class->shown(),
            'url' => rtrim(Hosts::publicUrl(), '/').'/class/'.$class->shown(),
            'seats' => $class->seats,
            // As of the start of today: the teacher can't watch who joins.
            'used' => $class->usedForTeacher(),
            'status' => $class->status($class->usedForTeacher()),
            'expiresAt' => $class->expires_at->utc()->toIso8601ZuluString(),
            'closedAt' => $class->closed_at?->utc()->toIso8601ZuluString(),
            'createdAt' => $class->created_at?->utc()->toIso8601ZuluString(),
        ];
    }
}
