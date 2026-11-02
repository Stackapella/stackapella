<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\School\SchoolCodes;
use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\SchoolRedemption;
use App\Models\SchoolTeacher;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin > School (docs/school-offer-plan.md): every class code with its teacher and seats, a code's
 * redemptions, Close for a code and End for one student's year (misuse).
 */
final class SchoolController extends Controller
{
    public function __construct(private readonly SchoolCodes $codes) {}

    public function index(): JsonResponse
    {
        $codes = $this->codes->all();
        // Each teacher's seats over all their codes (within the 500 listed).
        $taken = $codes->groupBy('teacher_id')->map(fn ($theirs): int => (int) $theirs->sum(fn (SchoolClass $c): int => $c->allowanceTaken()))->all();

        return response()->json(['data' => $codes->map(fn (SchoolClass $c): array => self::row($c, $taken[$c->teacher_id] ?? null))->values()]);
    }

    public function show(SchoolClass $class): JsonResponse
    {
        $class->load(['teacher:id,name,email', 'teacherRecord', 'redemptions' => fn ($q) => $q->with('user:id,name,email')->latest('id')]);

        return response()->json(['data' => [...self::row($class, $class->teacher_id !== null ? SchoolCodes::seatsTaken($class->teacher_id) : null), 'redemptions' => $class->redemptions->map(static fn (SchoolRedemption $r): array => [
            'id' => $r->sqid(),
            'user' => ['id' => $r->user->sqid(), 'name' => $r->user->name, 'email' => $r->user->email],
            'at' => $r->created_at?->utc()->toIso8601ZuluString(),
            'plusUntil' => $r->plus_until->utc()->toIso8601ZuluString(),
            'endedAt' => $r->ended_at?->utc()->toIso8601ZuluString(),
        ])->values()]]);
    }

    public function update(Request $request, SchoolClass $class, #[CurrentUser] User $admin): JsonResponse
    {
        $request->validate(['action' => ['required', 'in:close'], 'reason' => ['sometimes', 'nullable', 'string', 'max:300']]);
        $reason = $request->input('reason');

        $class = $this->codes->close($class, $admin, is_string($reason) ? $reason : null)->load(['teacher:id,name,email', 'teacherRecord']);

        return response()->json(['data' => self::row($class, $class->teacher_id !== null ? SchoolCodes::seatsTaken($class->teacher_id) : null)]);
    }

    /**
     * Approve a teacher (their codes start working), take it back, or set their seat allowance.
     */
    public function teacher(Request $request, User $teacher, #[CurrentUser] User $admin): JsonResponse
    {
        $request->validate(['approved' => ['sometimes', 'boolean'], 'seatAllowance' => ['sometimes', 'integer', 'min:0', 'max:5000'], 'reason' => ['sometimes', 'nullable', 'string', 'max:300']]);
        $reason = $request->input('reason');
        $record = $this->codes->teacher($teacher, $admin, $request->has('approved') ? $request->boolean('approved') : null, $request->has('seatAllowance') ? (int) $request->input('seatAllowance') : null, is_string($reason) ? $reason : null);

        return response()->json(['data' => self::teacherView($teacher, $record, SchoolCodes::seatsTaken($teacher->id))]);
    }

    public function end(Request $request, SchoolRedemption $redemption, #[CurrentUser] User $admin): JsonResponse
    {
        $request->validate(['reason' => ['sometimes', 'nullable', 'string', 'max:300']]);
        $reason = $request->input('reason');
        $redemption = $this->codes->end($redemption->load('user'), $admin, is_string($reason) ? $reason : null);

        return response()->json(['data' => ['id' => $redemption->sqid(), 'endedAt' => $redemption->ended_at?->utc()->toIso8601ZuluString()]]);
    }

    /**
     * @return array{id: string, name: string, email: string|null, approved: bool, seatAllowance: int, seatsTaken: int}
     */
    private static function teacherView(User $teacher, ?SchoolTeacher $record, int $taken): array
    {
        return ['id' => $teacher->sqid(), 'name' => $teacher->name, 'email' => $teacher->email, 'approved' => $record?->approved_at !== null,
            'seatAllowance' => $record->seat_allowance ?? SchoolTeacher::ALLOWANCE, 'seatsTaken' => $taken];
    }

    /**
     * @return array<string, mixed>
     */
    private static function row(SchoolClass $class, ?int $taken): array
    {
        return [
            'id' => $class->sqid(),
            'name' => $class->name,
            'code' => $class->shown(),
            'teacher' => $class->teacher !== null ? self::teacherView($class->teacher, $class->teacherRecord, $taken ?? 0) : null,
            'seats' => $class->seats,
            'used' => $class->used,
            'status' => $class->status(),
            'createdAt' => $class->created_at?->utc()->toIso8601ZuluString(),
            'expiresAt' => $class->expires_at->utc()->toIso8601ZuluString(),
            'closedAt' => $class->closed_at?->utc()->toIso8601ZuluString(),
            'closedBy' => $class->closed_by,
        ];
    }
}
