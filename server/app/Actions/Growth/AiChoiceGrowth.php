<?php

declare(strict_types=1);

namespace App\Actions\Growth;

use App\Support\Accounts\Age;
use App\Support\Accounts\AiChoice;
use App\Support\Stats\GrowthStart;
use App\Support\Stats\ReportDay;

/**
 * Admin > Growth's "AI choice" card (admin.growth.ai; docs/ai-optional-plan.md v2, "Measuring from behaviour"): the
 * card's events and its try and not rates, AI used, Settings' switches, and engagement by state (on, off, unset), by
 * the age band kept at sign-up (and guests), over 28 or 90 days.
 * Anonymous daily counts only (AiChoice), from the growth fresh start; any count under FLOOR is null ("too few"), and a
 * share null while either side is, so a small band never stands for a person.
 */
final class AiChoiceGrowth
{
    public const FLOOR = 3;

    private const ENGAGEMENT = ['backD1' => 'back_d1', 'backW1' => 'back_w1', 'backD7' => 'back_d7', 'backW4' => 'back_w4'];

    private const LESSONS = ['lessons0' => '0', 'lessons1to2' => '1-2', 'lessons3to5' => '3-5', 'lessons6plus' => '6+'];

    /**
     * @return array<string, mixed>
     */
    public function build(string $period): array
    {
        $days = $period === '90d' ? 90 : 28;
        $to = ReportDay::now()->startOfDay();
        $from = $to->copy()->subDays($days - 1);
        $counts = app(GrowthStart::class)->counts()->whereBetween('day', [$from->toDateString(), $to->toDateString()])
            ->where(static fn ($q) => $q->where('kind', 'like', 'ai\\_card:%')->orWhere('kind', 'like', 'ai\\_use:%')->orWhere('kind', 'like', 'ai\\_switch:%')->orWhere('kind', 'like', 'aic:%'))
            ->selectRaw('kind, sum(count) as n')->groupBy('kind')->pluck('n', 'kind')->map(static fn (mixed $n): int => (int) $n)->all();

        $bands = array_map(fn (string $band): array => ['band' => $band, ...$this->row($counts, [$band])], [...Age::BANDS, AiChoice::GUEST]);

        return [
            'period' => $days === 90 ? '90d' : '28d',
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'timezone' => ReportDay::zone(),
            'floor' => self::FLOOR,
            'bands' => $bands,
            // Every band, guests, and the accounts with no birth date.
            'total' => $this->row($counts, [...Age::BANDS, AiChoice::GUEST, AiChoice::NO_BAND]),
        ];
    }

    /**
     * One band's (or the total's) numbers, each under the floor suppressed.
     *
     * @param  array<string, int>  $counts
     * @param  list<string>  $bands
     * @return array<string, mixed>
     */
    private function row(array $counts, array $bands): array
    {
        $sum = static fn (string $prefix): int => array_sum(array_map(static fn (string $band): int => $counts["{$prefix}:{$band}"] ?? 0, $bands));
        $shown = static fn (int $n): ?int => $n >= self::FLOOR ? $n : null;
        $rate = static fn (int $part, int $whole): ?float => $part >= self::FLOOR && $whole >= self::FLOOR ? round($part / $whole, 3) : null;
        $cardShown = $sum('ai_card:shown');
        $engagement = [];

        foreach (['on', 'off', 'unset'] as $state) {
            $row = ['state' => $state];

            foreach (self::ENGAGEMENT as $key => $kind) {
                $row[$key] = $shown($sum("aic:{$kind}:{$state}"));
            }

            foreach (self::LESSONS as $key => $bucket) {
                $row[$key] = $shown(array_sum(array_map(static fn (string $band): int => $counts["aic:wk1_lessons:{$state}:{$band}:{$bucket}"] ?? 0, $bands)));
            }

            $engagement[] = $row;
        }

        return [
            'cardShown' => $shown($cardShown),
            'cardTry' => $shown($sum('ai_card:try')),
            'cardNot' => $shown($sum('ai_card:not')),
            'cardClosed' => $shown($sum('ai_card:closed')),
            'tryRate' => $rate($sum('ai_card:try'), $cardShown),
            'notRate' => $rate($sum('ai_card:not'), $cardShown),
            'choirStart' => $shown($sum('ai_use:choir_start')),
            'choirHeard' => $shown($sum('ai_use:choir_heard')),
            'guideHeard' => $shown($sum('ai_use:guide_heard')),
            'switchOnOff' => $shown($sum('ai_switch:on_off')),
            'switchOffOn' => $shown($sum('ai_switch:off_on')),
            'engagement' => $engagement,
        ];
    }
}
