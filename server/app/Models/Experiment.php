<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Stats\ReportDay;
use Carbon\CarbonImmutable;
use Database\Factories\ExperimentFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An A/B test (docs/experiments-plan.md): its arms, the step it's read by, its guardrails and who
 * may join, defined in Admin. Its results are only ever daily totals per arm (usage_counts).
 *
 * @property int $id
 * @property string $key
 * @property string $what
 * @property string $hypothesis
 * @property list<array{arm: string, weight: int}> $arms
 * @property string $metric
 * @property list<string> $guardrails
 * @property array<string, bool|string> $audience new, skin, size, signedIn, firstWeek (Hillel's OK to enrol a singer in their first week)
 * @property list<string> $excludes
 * @property int $planned_per_arm
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property string|null $winner
 * @property Carbon|null $closed_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[UseFactory(ExperimentFactory::class)]
final class Experiment extends Model
{
    /** @use HasFactory<ExperimentFactory> */
    use HasFactory;

    public const DRAFT = 'draft';

    public const RUNNING = 'running';

    public const READING = 'reading';

    public const DECIDED = 'decided';

    public const CLOSED = 'closed';

    /** The read date: a week after enrolment stops, so day-2 and week-1 returns have matured. */
    public const READ_AFTER_DAYS = 7;

    protected function casts(): array
    {
        return [
            'arms' => 'array', 'guardrails' => 'array', 'audience' => 'array', 'excludes' => 'array',
            'starts_on' => 'date', 'ends_on' => 'date', 'closed_at' => 'datetime',
        ];
    }

    /**
     * @return list<string>
     */
    public function armNames(): array
    {
        return array_values(array_map(static fn (array $arm): string => (string) $arm['arm'], $this->arms));
    }

    /**
     * The day enrolment stopped: its end day, once passed (the counts never stop it).
     */
    public function enrolUntil(): ?string
    {
        $today = ReportDay::today();

        return $this->starts_on !== null && $this->ends_on !== null && $this->ends_on->toDateString() < $today ? $this->ends_on->toDateString() : null;
    }

    public function readFrom(): ?string
    {
        $until = $this->enrolUntil();

        return $until !== null ? CarbonImmutable::parse($until)->addDays(self::READ_AFTER_DAYS)->toDateString() : null;
    }

    public function status(): string
    {
        return match (true) {
            $this->closed_at !== null => self::CLOSED,
            $this->winner !== null => self::DECIDED,
            $this->starts_on === null => self::DRAFT,
            $this->enrolUntil() !== null => self::READING,
            default => self::RUNNING,
        };
    }

    /**
     * New devices may still join: started, its end day not passed, not decided or closed.
     */
    public function enrolling(): bool
    {
        return $this->status() === self::RUNNING && $this->starts_on !== null && $this->starts_on->toDateString() <= ReportDay::today();
    }
}
