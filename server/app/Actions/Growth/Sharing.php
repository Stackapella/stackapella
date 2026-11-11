<?php

declare(strict_types=1);

namespace App\Actions\Growth;

use App\Actions\Counts\Funnel;
use App\Support\Stats\ReportDay;
use Illuminate\Support\Facades\DB;

/**
 * How often singers pass the app on, and what comes of it (docs/retention-plan.md, "Sharing"; Hillel, 2026-11-11: "I
 * wanna make sure people are sharing"). For each surface over a week: the shares by method (sh:<surface>.<method>),
 * the shared pages opened (so:<surface>), and the devices that arrived by its tag (share-<surface>; a tool's
 * share-<slug>) and went on to a first lesson and an account; and the four headline measures beside last week's.
 * Anonymous daily totals only, every number a sum over days.
 */
final class Sharing
{
    /** The sources' funnel steps a share's arrivals are read by. */
    private const FOLLOWED = ['arrivals' => Funnel::ARRIVALS, 'lesson1' => ['f_lesson1'], 'signedUp' => ['f_account']];

    /**
     * The week to today (report timezone), and the week before it for its moves.
     *
     * @return array{from: string, to: string, floor: int, headline: array<string, mixed>, before: array<string, mixed>, surfaces: list<array<string, mixed>>}
     */
    public function build(): array
    {
        $to = ReportDay::now()->startOfDay();
        $from = $to->copy()->subDays(6);
        $week = $this->read($from->toDateString(), $to->toDateString());
        $before = $this->read($from->copy()->subDays(7)->toDateString(), $from->copy()->subDay()->toDateString());

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'floor' => (int) config('counts.dropoffs.floor', 30),
            'headline' => $week['headline'],
            'before' => $before['headline'],
            'surfaces' => $week['surfaces'],
        ];
    }

    /**
     * @return array{headline: array<string, mixed>, surfaces: list<array<string, mixed>>}
     */
    private function read(string $from, string $to): array
    {
        $kinds = DB::table('usage_counts')->whereBetween('day', [$from, $to])
            ->where(static fn ($q) => $q->where('kind', 'like', 'sh:%')->orWhere('kind', 'like', 'so:%')->orWhere('kind', 'app_week'))
            ->selectRaw('kind, sum(count) as n')->groupBy('kind')->pluck('n', 'kind')->map(static fn (mixed $n): int => (int) $n)->all();
        $followed = [];

        foreach (DB::table('usage_counts')->whereBetween('day', [$from, $to])->where(static fn ($q) => $q->where('source', 'like', 'share-%')->orWhere('source', 'harmony'))
            ->whereIn('kind', array_merge(...array_values(self::FOLLOWED)))->selectRaw('source, kind, sum(count) as n')->groupBy('source', 'kind')->get() as $row) {
            // A shared song's links were read as "harmony" before 2026-11-11: the song surface either way.
            $tag = (string) $row->source === 'harmony' ? 'share-song' : (string) $row->source;
            $followed[$tag][(string) $row->kind] = ($followed[$tag][(string) $row->kind] ?? 0) + (int) $row->n;
        }

        $surfaces = [];
        foreach (self::surfaces() as $surface => $tag) {
            $methods = [];
            foreach ((array) config('counts.share_methods') as $method) {
                $methods[$method] = $kinds['sh:'.$surface.'.'.$method] ?? 0;
            }
            $step = static fn (string $name): int => array_sum(array_map(static fn (string $k): int => $followed[$tag][$k] ?? 0, self::FOLLOWED[$name]));
            $shares = array_sum($methods);
            $arrivals = $step('arrivals');
            $surfaces[] = [
                'surface' => $surface,
                'shares' => $shares,
                'methods' => $methods,
                'opened' => in_array($surface, (array) config('counts.shared_pages'), true) ? ($kinds['so:'.$surface] ?? 0) : null,
                'arrivals' => $arrivals,
                'lesson1' => $step('lesson1'),
                'signedUp' => $step('signedUp'),
                'arrivalsPerShare' => $shares > 0 ? round($arrivals / $shares, 2) : null,
            ];
        }

        $active = $kinds['app_week'] ?? 0;
        $shares = array_sum(array_column($surfaces, 'shares'));
        $arrivals = array_sum(array_column($surfaces, 'arrivals'));
        $lesson1 = array_sum(array_column($surfaces, 'lesson1'));

        return [
            'headline' => [
                'active' => $active,
                'shares' => $shares,
                'arrivals' => $arrivals,
                // Shares per 100 active singers; arrivals a share; the share of arrivals that sing a first lesson and
                // make an account; and "each 100 singers bring N" (arrivals reaching a first lesson, per 100 active).
                'sharesPer100' => $active > 0 ? round($shares * 100 / $active, 1) : null,
                'arrivalsPerShare' => $shares > 0 ? round($arrivals / $shares, 2) : null,
                'arrivalsThatSing' => $arrivals > 0 ? round($lesson1 / $arrivals, 3) : null,
                'arrivalsThatSignUp' => $arrivals > 0 ? round(array_sum(array_column($surfaces, 'signedUp')) / $arrivals, 3) : null,
                'eachHundredBring' => $active > 0 ? round($lesson1 * 100 / $active, 1) : null,
            ],
            'surfaces' => $surfaces,
        ];
    }

    /**
     * Every share surface with its arrivals' tag: share-<surface>, a free tool's share-<slug>.
     *
     * @return array<string, string>
     */
    private static function surfaces(): array
    {
        $out = [];
        foreach ((array) config('counts.share_surfaces') as $surface) {
            $out[(string) $surface] = 'share-'.$surface;
        }
        foreach ((array) config('tools.slugs') as $slug) {
            $out['tool-'.$slug] = 'share-'.$slug;
        }

        return $out;
    }
}
