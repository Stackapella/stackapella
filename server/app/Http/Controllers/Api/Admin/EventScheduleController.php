<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\EsConnection;
use App\Models\LessonBooking;
use App\Services\EventSchedule\EventSchedule;
use App\Support\Coaching\CoachingView;
use Illuminate\Http\JsonResponse;

/**
 * admin.eventSchedule.show: the integration's health (G5): connections by status, those ES stopped
 * accepting, and lessons the reconcile couldn't match or that failed to book (the last 30 days).
 */
final class EventScheduleController extends Controller
{
    public function __invoke(EventSchedule $es): JsonResponse
    {
        $counts = EsConnection::query()->selectRaw('status, count(*) as total, sum(webhook_failing_at is not null) as failing')->groupBy('status')->get()->keyBy('status');
        $broken = EsConnection::query()->with('user')->where('status', EsConnection::NEEDS_RECONNECT)->orderByDesc('failing_since')->limit(50)->get();
        $lessons = LessonBooking::query()->with(['engagement.singer', 'engagement.coach'])->whereNotNull('problem')->where('problem_at', '>', now()->subDays(30))->orderByDesc('problem_at')->limit(50)->get();

        return response()->json(['data' => [
            'configured' => $es->configured(),
            'fake' => $es->isFake(),
            'connections' => [
                'active' => (int) ($counts->get(EsConnection::ACTIVE)->total ?? 0),
                'needsReconnect' => (int) ($counts->get(EsConnection::NEEDS_RECONNECT)->total ?? 0),
                'webhooksFailing' => (int) $counts->sum('failing'),
            ],
            'needsReconnect' => $broken->map(fn (EsConnection $c): array => [
                'user' => CoachingView::person($c->user),
                'mode' => $c->mode,
                'since' => $c->failing_since?->utc()->toIso8601ZuluString(),
                'error' => $c->last_error,
            ])->all(),
            'lessons' => $lessons->map(fn (LessonBooking $l): array => [
                'engagementId' => $l->engagement->sqid(),
                'coach' => CoachingView::person($l->engagement->coach),
                'singer' => CoachingView::person($l->engagement->singer),
                'startsAt' => $l->starts_at->utc()->toIso8601ZuluString(),
                'status' => $l->engagement->status,
                'problem' => (string) $l->problem,
                'note' => $l->problem_note,
                'at' => $l->problem_at?->utc()->toIso8601ZuluString(),
            ])->all(),
        ]]);
    }
}
