<?php

declare(strict_types=1);

namespace App\Actions\OwnChoir;

use App\Models\Setting;
use App\Notifications\Admin\OpsAlert;
use App\Support\Admin\Admins;
use App\Support\Ops\Readiness;
use Illuminate\Support\Facades\Notification;

/**
 * worker.choir.status (the GPU review, 2026-11-21): the worker's own spending, kept for Admin (setting
 * ops.choir_worker), and the admins told when it pauses (once per pause).
 */
final class RecordChoirWorkerStatus
{
    public const SETTING = 'ops.choir_worker';

    /**
     * @param  array{paused: string|null, spentTodayUsd: float|int, spentMonthUsd: float|int, limitDayUsd: float|int, limitMonthUsd: float|int}  $status
     */
    public function handle(array $status): void
    {
        $before = (array) (Setting::query()->where('key', self::SETTING)->first()->value ?? []);
        Readiness::stamp(self::SETTING, [...$status, 'at' => now()->utc()->toIso8601ZuluString()]);

        if ($status['paused'] !== null && ($before['paused'] ?? null) !== $status['paused'] && ($admins = app(Admins::class)->all())->isNotEmpty()) {
            $daily = $status['paused'] === 'daily_ceiling';
            Notification::send($admins, new OpsAlert(__('ops.choir_worker_paused.subject'), __('ops.choir_worker_paused.title'), [__('ops.choir_worker_paused.line', [
                'which' => $daily ? 'daily ceiling' : 'monthly ceiling',
                'spent' => (string) ($daily ? $status['spentTodayUsd'] : $status['spentMonthUsd']),
                'limit' => (string) ($daily ? $status['limitDayUsd'] : $status['limitMonthUsd']),
            ])]));
        }
    }
}
