<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Setting;
use App\Notifications\Admin\OpsAlert;
use App\Support\Admin\Admins;
use App\Support\Ops\Check;
use App\Support\Ops\Readiness;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * php artisan app:readiness [--json] [--alert]: every check, pass, warn or fail with its fix.
 * --alert (every 10 minutes) emails the admins only when a check changes (a new failure counts).
 */
final class ReadinessCheck extends Command
{
    private const STATE = 'ops.readiness_state';

    protected $signature = 'app:readiness {--json} {--alert : Email the admins about checks that changed}';

    protected $description = 'Check that everything Stackapella needs is in place';

    public function handle(Readiness $readiness, Admins $admins): int
    {
        $checks = $readiness->run();

        if ($this->option('alert')) {
            $this->emailChanges($checks, $admins);
        }

        if ($this->option('json')) {
            $this->line((string) json_encode(array_map(static fn (Check $c): array => $c->toArray(), $checks), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(['Group', 'Check', 'Result', 'Details', 'Fix'], array_map(static fn (Check $c): array => [$c->group, $c->label, strtoupper($c->status), mb_strimwidth($c->message, 0, 90, '...'), $c->fix], $checks));
            $summary = Readiness::summary($checks);
            $this->line("{$summary['pass']} passed, {$summary['warn']} warnings, {$summary['fail']} failed.");
        }

        // On the schedule (--alert) the email is the alarm: a failing check isn't the job failing,
        // so the scheduler and Sentry don't raise it again every ten minutes.
        return Readiness::summary($checks)['fail'] === 0 || $this->option('alert') ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  list<Check>  $checks
     */
    private function emailChanges(array $checks, Admins $admins): void
    {
        $before = (array) (Setting::query()->where('key', self::STATE)->first()->value ?? []);
        $state = [];
        $lines = [];

        foreach ($checks as $check) {
            $key = $check->group.'.'.$check->key;
            $state[$key] = $check->status;
            $was = $before[$key] ?? null;

            if ($was !== $check->status && ($was !== null || $check->status === Check::FAIL)) {
                $lines[] = __('ops.readiness.line', ['label' => $check->label, 'from' => $was ?? __('ops.readiness.new'), 'to' => $check->status, 'message' => $check->message]).($check->fix !== '' ? ' '.__('ops.readiness.fix', ['fix' => $check->fix]) : '');
            }
        }

        Readiness::stamp(self::STATE, $state);

        if ($lines !== [] && ($to = $admins->all())->isNotEmpty()) {
            Notification::send($to, new OpsAlert(__('ops.readiness.subject', ['count' => count($lines)]), __('ops.readiness.title'), $lines));
            $this->info(count($lines).' changes emailed.');
        }
    }
}
