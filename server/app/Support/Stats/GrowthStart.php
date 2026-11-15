<?php

declare(strict_types=1);

namespace App\Support\Stats;

use App\Models\AuditLog;
use App\Models\Flag;
use App\Support\Flags;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Growth's fresh start (Hillel, 2026-11-15: "i dont trust any of the growth data since we recently fixed for
 * spam", then "let's make it a switch i can set once and forget about"): while the growth_fresh_start switch is
 * on, every growth view in Admin and the growth export count only from the start of the day (Jerusalem time) it
 * was turned on. Nothing is deleted; off shows all history again.
 *
 * The day is the moment the switch last went from off to on, read from its audit entry, so saving it on again
 * (a new reason) never moves it. Counts are filed by ReportDay's day, so a count's day is compared as a date;
 * timestamps (sign-ups, hours) from Jerusalem's midnight that day.
 */
final class GrowthStart
{
    public const FLAG = 'growth_fresh_start';

    public const ZONE = 'Asia/Jerusalem';

    /** @var array{day: string|null}|null */
    private ?array $kept = null;

    public function __construct(private readonly Flags $flags) {}

    /** The first day counted (Y-m-d), or null while the switch is off. */
    public function day(): ?string
    {
        $this->kept ??= ['day' => $this->read()];

        return $this->kept['day'];
    }

    /** That day's start in Jerusalem, as a UTC moment for the database; null while off. */
    public function moment(): ?CarbonImmutable
    {
        $day = $this->day();

        return $day === null ? null : CarbonImmutable::parse($day, self::ZONE)->startOfDay()->utc();
    }

    /** A first day, moved up to the fresh start when it's earlier. */
    public function clip(string $day): string
    {
        $start = $this->day();

        return $start !== null && substr($day, 0, 10) < $start ? $start : $day;
    }

    /** A first moment, moved up to the fresh start when it's earlier. */
    public function clipMoment(CarbonImmutable $moment): CarbonImmutable
    {
        $start = $this->moment();

        return $start !== null && $moment->lt($start) ? $start : $moment;
    }

    /** A window's first day (in its own zone), moved up to the fresh start when it's earlier. */
    public function clipDay(Carbon $day): Carbon
    {
        $start = $this->day();

        return $start !== null && $day->toDateString() < $start ? Carbon::parse($start, $day->getTimezone())->startOfDay() : $day;
    }

    /** A Monday-to-Sunday window's first Monday, moved up to the week the fresh start is in. */
    public function clipWeek(Carbon $monday): Carbon
    {
        $first = $this->clipDay($monday);

        return $first === $monday ? $monday : $first->startOfWeek(Carbon::MONDAY);
    }

    /**
     * A table of day-filed counts (usage_counts, experiment_counts, active_singer_days), from the fresh start.
     */
    public function counts(string $table = 'usage_counts', string $column = 'day'): Builder
    {
        $query = DB::table($table);
        $start = $this->day();

        return $start === null ? $query : $query->where($column, '>=', $start);
    }

    /** The hourly counts (usage_count_hours), from the fresh start's moment. */
    public function hours(): Builder
    {
        return $this->since(DB::table('usage_count_hours'), 'hour');
    }

    /**
     * A query on timestamped rows (sign-ups' created_at, the hours), from the fresh start's moment.
     *
     * @template TQuery of \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<*>
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    public function since(mixed $query, string $column = 'created_at'): mixed
    {
        $moment = $this->moment();

        return $moment === null ? $query : $query->where($column, '>=', $moment->format('Y-m-d H:i:s'));
    }

    private function read(): ?string
    {
        if (! $this->flags->on(self::FLAG)) {
            return null;
        }

        $on = AuditLog::query()->where('action', 'flag.'.self::FLAG)->latest('id')->get(['before', 'after', 'created_at'])
            ->first(static fn (AuditLog $log): bool => ($log->before['value'] ?? 'off') === 'off' && ($log->after['value'] ?? null) === 'on');
        $at = $on->created_at ?? Flag::query()->where('name', self::FLAG)->value('updated_at');

        return $at === null ? null : CarbonImmutable::parse($at)->setTimezone(self::ZONE)->toDateString();
    }
}
