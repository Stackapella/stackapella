<?php

declare(strict_types=1);

namespace App\Actions\Growth;

use App\Enums\OwnChoirStatus;
use App\Models\OwnChoirRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Admin > Choir's Live panel (admin.choir.live): the queue, today's spend against the daily meter, the month's GPU time
 * and its cost, the last day's failures, the latest requests and the worker's last signs of life. Operations numbers,
 * nothing about anyone: a request is its song and its facts, never its singer.
 */
final class ChoirLive
{
    public const POLL = 'own_choir:worker_polled_at';

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $now = now();
        $open = OwnChoirRequest::query()->whereIn('status', [OwnChoirStatus::Queued->value, OwnChoirStatus::Rendering->value])->get(['id', 'status', 'starts_after', 'created_at', 'remake', 'parent_request_id']);
        $queued = $open->where('status', OwnChoirStatus::Queued);
        $waiting = $queued->filter(static fn (OwnChoirRequest $r): bool => $r->starts_after !== null && $r->starts_after->isFuture());
        $oldest = $queued->reject(static fn (OwnChoirRequest $r): bool => $waiting->contains($r))->min('created_at');
        $day = OwnChoirRequest::query()->where('first_claimed_at', '>=', $now->copy()->startOfDay());
        $readyToday = OwnChoirRequest::query()->where('ready_at', '>=', $now->copy()->startOfDay());
        $monthGpu = (float) OwnChoirRequest::query()->where('first_claimed_at', '>=', $now->copy()->startOfMonth())->sum('gpu_seconds');

        $failures = OwnChoirRequest::query()->where('status', OwnChoirStatus::Failed->value)->where('updated_at', '>=', $now->copy()->subDay())
            ->selectRaw('failure, failure_detail, count(*) as n')->groupBy('failure', 'failure_detail')->orderByDesc('n')->get()
            ->map(static fn (OwnChoirRequest $r): array => ['failure' => (string) $r->failure, 'failureDetail' => $r->failure_detail, 'n' => (int) $r->getAttribute('n')])->all();

        $recent = OwnChoirRequest::query()->with('templateVersion.template')->where('status', '!=', OwnChoirStatus::Deleted->value)->orWhere('remake', true)->latest('id')->limit(30)->get()
            ->map(static fn (OwnChoirRequest $r): array => [
                'songId' => (string) $r->templateVersion?->template?->slug,
                'kind' => $r->remake ? 'remake' : ($r->parent_request_id !== null ? 'friend' : 'own'),
                'status' => $r->status->value,
                'failure' => $r->failure,
                'failureDetail' => $r->failure_detail,
                'minutes' => round($r->part_minutes, 1),
                'gpuSeconds' => round($r->gpu_seconds, 1),
                'voicing' => $r->voicing,
                'quality' => (string) (((array) ($r->sung ?? []))['quality'] ?? 'standard'),
                'paid' => $r->priority === 1,
                'createdAt' => $r->created_at?->utc()->toIso8601ZuluString(),
                'readyAt' => $r->ready_at?->utc()->toIso8601ZuluString(),
                // The render's own time, apart from its wait: from its first claim to ready.
                'seconds' => $r->first_claimed_at !== null && $r->ready_at !== null ? (int) $r->first_claimed_at->diffInSeconds($r->ready_at) : null,
            ])->values()->all();

        $polled = Cache::get(self::POLL);
        $lastClaim = OwnChoirRequest::query()->max('first_claimed_at');

        return [
            'asOf' => $now->utc()->toIso8601ZuluString(),
            'queue' => [
                'queued' => $queued->count() - $waiting->count(),
                'rendering' => $open->where('status', OwnChoirStatus::Rendering)->count(),
                'waitingForTomorrow' => $waiting->count(),
                'oldestWaitSeconds' => $oldest instanceof Carbon ? (int) $oldest->diffInSeconds($now) : null,
                'remakes' => $open->where('remake', true)->count(),
                'friends' => $open->filter(static fn (OwnChoirRequest $r): bool => $r->parent_request_id !== null && ! $r->remake)->count(),
            ],
            'today' => [
                'partMinutes' => round((float) (clone $day)->sum('part_minutes'), 1),
                'dailyPartMinutes' => (int) config('own_choir.daily_part_minutes'),
                'gpuSeconds' => round((float) (clone $day)->sum('gpu_seconds'), 1),
                'made' => (clone $readyToday)->count(),
                'failed' => OwnChoirRequest::query()->where('status', OwnChoirStatus::Failed->value)->where('updated_at', '>=', $now->copy()->startOfDay())->count(),
            ],
            'month' => [
                'gpuSeconds' => round($monthGpu, 1),
                'cost' => round($monthGpu * (float) config('own_choir.gpu_cost_per_second'), 2),
                'currency' => 'usd',
            ],
            'failures' => $failures,
            'recent' => $recent,
            'worker' => [
                'lastClaimAt' => $lastClaim !== null ? Carbon::parse((string) $lastClaim)->utc()->toIso8601ZuluString() : null,
                'lastPollAt' => is_string($polled) ? $polled : null,
            ],
        ];
    }
}
