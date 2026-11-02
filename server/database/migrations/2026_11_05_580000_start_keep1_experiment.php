<?php

declare(strict_types=1);

use App\Support\Stats\ReportDay;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * keep1, the first-week recap's keep row (docs/account-nudges-plan.md), started by the deploy that
 * runs this: Hillel's "Start it" (2026-11-01), with its guardrails f_day14 and f_plus, for guests,
 * a first-week device included (the row shows only from day 7 after arrival, but a late course
 * start, or songs only, still reads as first week on the device). Safe to run again: a keep1
 * already started (or stopped, or closed) is left as it is; a draft is started with these
 * settings, and a missing one is made and started.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Production's state, not the schema: tests and dev databases keep keep1 a draft, so no
        // test or local flow is drawn into an arm by surprise.
        if (! app()->isProduction()) {
            return;
        }

        $settings = [
            'arms' => json_encode([['arm' => 'a', 'weight' => 50], ['arm' => 'b', 'weight' => 50]]),
            'metric' => 'f_account',
            'guardrails' => json_encode(['f_day14', 'f_plus']),
            'audience' => json_encode(['signedIn' => false, 'firstWeek' => true]),
            'starts_on' => ReportDay::today(),
            'updated_at' => now(),
        ];
        $keep1 = DB::table('experiments')->where('key', 'keep1')->first(['starts_on']);

        if ($keep1 === null) {
            DB::table('experiments')->insert([
                'key' => 'keep1',
                'what' => 'The first-week recap with a keep row (B) or as today (A)',
                'hypothesis' => 'A keep row on the first-week recap gets more people to make an account.',
                'excludes' => json_encode([]),
                'planned_per_arm' => 1500,
                'created_at' => now(),
                ...$settings,
            ]);
        } elseif ($keep1->starts_on === null) {
            DB::table('experiments')->where('key', 'keep1')->whereNull('starts_on')->update($settings);
        } else {
            return;
        }

        Cache::forget('experiments.all');
        Cache::forget('funnel.current');
    }

    public function down(): void
    {
        // Left in place: its counts belong to it, and stopping it is the registry's job.
    }
};
