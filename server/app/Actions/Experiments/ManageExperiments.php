<?php

declare(strict_types=1);

namespace App\Actions\Experiments;

use App\Actions\Counts\Funnel;
use App\Exceptions\Refusal;
use App\Models\AuditLog;
use App\Models\Experiment;
use App\Models\User;
use App\Support\Stats\ReportDay;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Admin > Experiments (docs/experiments-plan.md, slice 1): an admin defines a test as a draft, edits
 * it while it's a draft, then starts it, stops it, names the winner and closes it. Each change is
 * audited and takes effect within the minute (the registry's cache is forgotten).
 */
final class ManageExperiments
{
    /**
     * @param  array<string, mixed>  $fields
     */
    public function create(User $admin, array $fields, ?string $ip): Experiment
    {
        $experiment = new Experiment;
        $experiment->forceFill(['key' => $fields['id'], ...self::columns($fields), 'created_by' => $admin->id])->save();
        AuditLog::record('experiment.created', $experiment, $admin, after: ['experiment' => $experiment->key], ip: $ip);
        self::forget();

        return $experiment;
    }

    /**
     * Its fields (only while a draft), or one step on: start, stop, decide (with `winner`) or close.
     *
     * @param  array<string, mixed>  $fields
     */
    public function update(User $admin, string $key, array $fields, ?string $action, ?string $winner, ?string $ip): Experiment
    {
        $experiment = DB::transaction(function () use ($key, $fields, $action, $winner): Experiment {
            $experiment = Experiment::query()->where('key', $key)->lockForUpdate()->first() ?? throw new Refusal(404, 'not_found');
            $status = $experiment->status();

            if ($fields !== []) {
                if ($status !== Experiment::DRAFT) {
                    throw new Refusal(409, 'experiment_started');
                }

                $experiment->forceFill(self::columns($fields));
            }

            match ($action) {
                null => null,
                'start' => $status === Experiment::DRAFT ? $experiment->forceFill(['starts_on' => ReportDay::today()]) : throw new Refusal(409, 'experiment_not_draft'),
                // Enrolment stops at once: its end day is yesterday (it's read a week on).
                'stop' => $status === Experiment::RUNNING ? $experiment->forceFill(['ends_on' => ReportDay::now()->subDay()->toDateString()]) : throw new Refusal(409, 'experiment_not_running'),
                'decide' => $status === Experiment::READING && in_array($winner, $experiment->armNames(), true) ? $experiment->forceFill(['winner' => $winner]) : throw new Refusal(409, 'experiment_not_reading'),
                default => $status !== Experiment::DRAFT && $status !== Experiment::RUNNING ? $experiment->forceFill(['closed_at' => now()]) : throw new Refusal(409, 'experiment_running'),
            };

            $experiment->save();

            return $experiment;
        });

        AuditLog::record('experiment.'.($action ?? 'edited'), $experiment, $admin, after: ['experiment' => $experiment->key, 'status' => $experiment->status(), 'winner' => $experiment->winner], ip: $ip);
        self::forget();

        return $experiment;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private static function columns(array $fields): array
    {
        $map = ['what' => 'what', 'hypothesis' => 'hypothesis', 'arms' => 'arms', 'metric' => 'metric', 'guardrails' => 'guardrails', 'audience' => 'audience', 'excludes' => 'excludes', 'plannedPerArm' => 'planned_per_arm'];
        $columns = [];

        foreach ($map as $field => $column) {
            if (array_key_exists($field, $fields)) {
                $columns[$column] = $fields[$field];
            }
        }

        return $columns;
    }

    private static function forget(): void
    {
        Cache::forget(Funnel::CACHE);
        Cache::forget('funnel.current');
    }
}
