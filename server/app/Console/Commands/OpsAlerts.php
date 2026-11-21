<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\SecurityEventType;
use App\Models\EmailSuppression;
use App\Models\Report;
use App\Models\SecurityEvent;
use App\Models\Setting;
use App\Models\ShareVideo;
use App\Notifications\Admin\OpsAlert;
use App\Support\Admin\Admins;
use App\Support\Ops\Readiness;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Hourly (docs/trust-and-safety-plan.md, "Alerts"): anything over its threshold emails the admins
 * at once, with where to handle it; each alert at most every 6 hours.
 */
final class OpsAlerts extends Command
{
    private const SENT = 'ops.alerts_sent';

    protected $signature = 'app:alerts';

    protected $description = 'Email the admins when sign-ups, reports, videos, payments, storage, limits or bounces are over their thresholds';

    public function handle(Admins $admins): int
    {
        $limits = (array) config('ops.alerts');
        $hour = now()->subHour();
        $events = fn (SecurityEventType $type): int => SecurityEvent::query()->where('type', $type)->where('created_at', '>', $hour)->count();
        $signals = [
            'sign_ups' => [$events(SecurityEventType::SignUp), $limits['sign_ups_an_hour'], '/admin/people'],
            'reports' => [Report::query()->where('created_at', '>', $hour)->count(), $limits['reports_an_hour'], '/admin/reports'],
            'videos' => [ShareVideo::query()->where('status', ShareVideo::QUEUED)->count(), $limits['videos_waiting'], '/admin/flags'],
            'failed_payments' => [$events(SecurityEventType::PaymentFailed), $limits['failed_payments_an_hour'], '/admin/flags'],
            'storage' => [(int) round(self::storedToday() / 1_000_000_000), $limits['storage_gb_a_day'], '/admin'],
            'limited' => [$events(SecurityEventType::RateLimited), $limits['limited_an_hour'], '/admin/security-events'],
            'suppressed' => [EmailSuppression::query()->where('suppressed_at', '>', now()->subDay())->count(), $limits['suppressed_a_day'], '/admin/flags'],
            'mail_waiting' => [DB::table('jobs')->where('queue', 'default')->where('created_at', '<', now()->subMinutes(5)->getTimestamp())->count(), $limits['mail_waiting'], '/admin'],
            'failed_jobs' => [DB::table('failed_jobs')->where('failed_at', '>', $hour)->count(), $limits['failed_jobs_an_hour'], '/admin'],
        ];

        $sent = (array) (Setting::query()->where('key', self::SENT)->first()->value ?? []);
        $lines = [];

        foreach ($signals as $name => [$value, $limit, $where]) {
            $last = isset($sent[$name]) && is_string($sent[$name]) ? strtotime($sent[$name]) : false;

            if ($value > (int) $limit && ($last === false || $last < now()->subHours((int) config('ops.alert_every_hours'))->getTimestamp())) {
                $lines[] = __('ops.alerts.'.$name, ['value' => $value, 'limit' => $limit]).' '.__('ops.alerts.where', ['url' => rtrim((string) config('app.url'), '/').$where]);
                $sent[$name] = now()->utc()->toIso8601ZuluString();
            }
        }

        if ($lines !== []) {
            Readiness::stamp(self::SENT, $sent);

            if (($to = $admins->all())->isNotEmpty()) {
                Notification::send($to, new OpsAlert(__('ops.alerts.subject', ['count' => count($lines)]), __('ops.alerts.title'), $lines));
            }
        }

        $this->info(count($lines).' alerts.');

        return self::SUCCESS;
    }

    /**
     * Bytes stored in the last day across everything people upload or we make from it (the abuse review: takes alone
     * missed shares, guides, ideas and stems). The buckets themselves can't be sized cheaply with our keys (no bucket-size
     * call in S3, and listing them is millions of requests), so their real size, versions included, is watched in
     * DigitalOcean's Spaces usage and its billing alert (docs/launch-open-gaps.md).
     */
    private static function storedToday(): int
    {
        $since = now()->subDay();

        return array_sum(array_map(static fn (string $table): int => (int) DB::table($table)->where('created_at', '>', $since)->sum('stored_bytes'),
            ['takes', 'harmonies', 'template_recordings', 'idea_voices', 'harmony_stems']));
    }
}
