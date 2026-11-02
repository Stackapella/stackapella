<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Counts\Funnel;
use App\Models\AuditLog;
use App\Models\Experiment;
use App\Notifications\Admin\OpsAlert;
use App\Support\Admin\Admins;
use App\Support\Stats\ReportDay;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * The experiments' one early stop (docs/experiments-plan.md; Hillel: a guardrail worse at 99%
 * stops a test): a running test whose B is worse than A on a guardrail beyond chance at 99% is
 * closed, so new devices aren't drawn and those in B go back to the current app. Audited, and the
 * admins are emailed.
 */
final class GuardExperiments extends Command
{
    protected $signature = 'app:experiments:guard';

    protected $description = 'Close any running experiment whose B is worse on a guardrail at 99%';

    public function handle(Funnel $funnel, Admins $admins): int
    {
        $closed = 0;

        foreach (Experiment::query()->whereNull('closed_at')->whereNull('winner')->whereNotNull('starts_on')->get() as $experiment) {
            if (! in_array($experiment->status(), [Experiment::RUNNING, Experiment::READING], true)) {
                continue;
            }

            $worse = $funnel->guardrailsWorse($experiment);

            if ($worse === []) {
                continue;
            }

            $experiment->forceFill(['ends_on' => $experiment->ends_on ?? ReportDay::now()->subDay()->toDateString(), 'closed_at' => now()])->save();
            AuditLog::record('experiment.guardrail_stop', $experiment, null, after: ['experiment' => $experiment->key, 'worse' => $worse]);
            $lines = array_map(static fn (array $w): string => __('ops.experiment_guard.line', ['kind' => $w['kind'], 'points' => $w['points'], 'low' => $w['low'], 'high' => $w['high']]), $worse);

            if (($to = $admins->all())->isNotEmpty()) {
                Notification::send($to, new OpsAlert(__('ops.experiment_guard.subject', ['id' => $experiment->key]), __('ops.experiment_guard.title', ['id' => $experiment->key]), $lines));
            }

            $closed++;
        }

        if ($closed > 0) {
            Cache::forget(Funnel::CACHE);
            Cache::forget('funnel.current');
        }

        $this->info("Closed {$closed} experiments on a guardrail.");

        return self::SUCCESS;
    }
}
