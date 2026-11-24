<?php

declare(strict_types=1);

namespace App\Actions\Growth;

use App\Models\CheckoutSession;
use App\Models\OwnChoirRequest;
use App\Support\OwnChoir\ChoirCounts;
use App\Support\Stats\GrowthStart;
use App\Support\Stats\ReportDay;
use Illuminate\Support\Carbon;

/**
 * Admin's Choir view (admin.choir.show; Hillel, 2026-11-23: "make sure this is all tracked so we can analyze over time
 * to improve"): the choir's anonymous daily counts over 7 or 30 days and the period before, read as trends, a funnel,
 * why clips failed, how each capture song does, where the gates fail, the tries a first song takes, the GPU time, and
 * Plus started from a choir moment. Numbers only, nothing about anyone.
 */
final class ChoirInsights
{
    public const FUNNEL = ['intro' => 'choir_intro_seen', 'take' => 'choir_take_started', 'sent' => 'choir_take_sent', 'requested' => 'own_choir_requested', 'ready' => 'own_choir_ready', 'seen' => 'choir_ready_seen', 'played' => 'choir_listen_played'];

    /**
     * @return array<string, mixed>
     */
    public function build(string $period): array
    {
        $days = $period === '7d' ? 7 : 30;
        $to = ReportDay::now()->startOfDay();
        $from = $to->copy()->subDays($days - 1);
        $beforeTo = $from->copy()->subDay();
        $beforeFrom = $beforeTo->copy()->subDays($days - 1);
        $kinds = self::kinds();

        $rows = app(GrowthStart::class)->counts()->whereBetween('day', [$from->toDateString(), $to->toDateString()])->whereIn('kind', $kinds)
            ->selectRaw('day, kind, sum(count) as n')->groupBy('day', 'kind')->orderBy('day')->get();
        $before = app(GrowthStart::class)->counts()->whereBetween('day', [$beforeFrom->toDateString(), $beforeTo->toDateString()])->whereIn('kind', $kinds)
            ->selectRaw('kind, sum(count) as n')->groupBy('kind')->pluck('n', 'kind')->map(static fn (mixed $n): int => (int) $n)->all();

        $now = [];
        $daily = [];

        foreach ($rows as $row) {
            $kind = (string) $row->kind;
            $n = (int) $row->n;
            $now[$kind] = ($now[$kind] ?? 0) + $n;
            $daily[(string) $row->day][] = ['kind' => $kind, 'n' => $n];
        }

        $pair = static fn (string $kind): array => ['n' => $now[$kind] ?? 0, 'before' => $before[$kind] ?? 0];

        $reasons = array_map(static fn (string $r): array => ['reason' => $r, ...$pair("choir_clip_{$r}")], [...ChoirCounts::REASONS, 'other']);
        usort($reasons, static fn (array $a, array $b): int => $b['n'] <=> $a['n']);
        $gates = [];

        foreach (['soprano', 'alto', 'tenor', 'bass', 'other'] as $part) {
            foreach (['written', 'low', 'full'] as $voicing) {
                if (($now["choir_gate_{$part}_{$voicing}"] ?? 0) > 0) {
                    $gates[] = ['part' => $part, 'voicing' => $voicing, 'n' => $now["choir_gate_{$part}_{$voicing}"]];
                }
            }
        }

        usort($gates, static fn (array $a, array $b): int => $b['n'] <=> $a['n']);

        return [
            'period' => $days === 7 ? '7d' : '30d',
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'floor' => (int) config('counts.dropoffs.floor', 30),
            'totals' => array_values(array_map(static fn (string $kind): array => ['kind' => $kind, ...$pair($kind)], array_values(array_filter($kinds, static fn (string $k): bool => ($now[$k] ?? 0) > 0 || ($before[$k] ?? 0) > 0)))),
            'daily' => array_map(static fn (string $day, array $kinds): array => ['date' => $day, 'kinds' => $kinds], array_keys($daily), array_values($daily)),
            'funnel' => array_values(array_map(static fn (string $step, string $kind): array => ['step' => $step, ...$pair($kind)], array_keys(self::FUNNEL), array_values(self::FUNNEL))),
            'reasons' => $reasons,
            'songs' => array_values(array_map(static fn (string $song): array => ['song' => $song, 'ok' => $now["choir_clip_ok_{$song}"] ?? 0, 'failed' => $now["choir_clip_fail_{$song}"] ?? 0], ChoirCounts::SONGS)),
            'gates' => $gates,
            'firstSuccess' => array_map(static fn (int $t): array => ['tries' => $t === 4 ? '4+' : (string) $t, 'n' => $now["choir_tries_{$t}"] ?? 0], [1, 2, 3, 4]),
            'renderSeconds' => $pair('choir_render_seconds'),
            'plusFromChoir' => ['n' => self::plusFrom($from, $to), 'before' => self::plusFrom($beforeFrom, $beforeTo)],
            'clipNumbers' => self::clipNumbers($from, $to),
            'gateNumbers' => self::gateNumbers($from, $to),
        ];
    }

    /**
     * Every kind the choir counts: the server's (ChoirCounts and own_choir_*) and the app's (choir_*).
     *
     * @return list<string>
     */
    public static function kinds(): array
    {
        $named = static fn (array $list): array => array_values(array_filter(array_map('strval', $list), static fn (string $k): bool => str_starts_with($k, 'choir_') || str_starts_with($k, 'own_choir_')));

        return array_values(array_unique([...ChoirCounts::kinds(), ...$named((array) config('counts.kinds')), ...$named((array) config('counts.server_kinds'))]));
    }

    /**
     * The period's latest clips that couldn't be used, with the worker's measures (detailNumbers): the reason, the day
     * and the numbers, never whose.
     *
     * @return list<array{detail: string|null, date: string, numbers: list<array{name: string, value: float}>}>
     */
    private static function clipNumbers(Carbon $from, Carbon $to): array
    {
        return array_values(OwnChoirRequest::query()->where('failure', 'clip_unusable')->whereNotNull('failure_numbers')
            ->whereBetween('updated_at', [ReportDay::start($from->toDateString()), ReportDay::end($to->toDateString())])->latest('updated_at')->latest('id')->limit(50)
            ->get(['id', 'failure_detail', 'failure_numbers', 'updated_at'])
            ->map(static fn (OwnChoirRequest $r): array => [
                'detail' => $r->failure_detail,
                'date' => $r->updated_at?->copy()->setTimezone(ReportDay::zone())->toDateString() ?? $to->toDateString(),
                'numbers' => array_values(array_map(static fn (array $n): array => ['name' => (string) $n['name'], 'value' => (float) $n['value']], (array) $r->failure_numbers)),
            ])->all());
    }

    /**
     * The period's latest jobs with the worker's pitch numbers: the day, how it ended, each gate failure's and each part's
     * worst notes in signed cents, never whose.
     *
     * @return list<array{date: string, failure: string|null, gateFailures: list<mixed>, partWorst: list<mixed>}>
     */
    private static function gateNumbers(Carbon $from, Carbon $to): array
    {
        return array_values(OwnChoirRequest::query()->whereNotNull('gate_numbers')
            ->whereBetween('updated_at', [ReportDay::start($from->toDateString()), ReportDay::end($to->toDateString())])->latest('updated_at')->latest('id')->limit(50)
            ->get(['id', 'failure', 'gate_numbers', 'updated_at'])
            ->map(static fn (OwnChoirRequest $r): array => [
                'date' => $r->updated_at?->copy()->setTimezone(ReportDay::zone())->toDateString() ?? $to->toDateString(),
                'failure' => $r->failure,
                'gateFailures' => array_values((array) ($r->gate_numbers['gateFailures'] ?? [])),
                'partWorst' => array_values((array) ($r->gate_numbers['partWorst'] ?? [])),
            ])->all());
    }

    /** Plus started from a choir moment: Checkouts completed in those days whose moment was a choir one. */
    private static function plusFrom(Carbon $from, Carbon $to): int
    {
        return CheckoutSession::query()->where('moment', 'like', 'choir\_%')->whereNotNull('completed_at')
            ->whereBetween('completed_at', [ReportDay::start($from->toDateString()), ReportDay::end($to->toDateString())])->count();
    }
}
