<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * ret1 (docs/home-returning-plan.md; Hillel through the coordinator, "Yes, as an experiment"): Home
 * as today (A) or with a "Today" line in the course card (B), as a draft in the registry, never
 * started here (the coordinator starts it in Admin > Experiments). The app asks for it only once a
 * device is past day 7, so no first-week singer is enrolled. Safe to run again: an existing ret1 is
 * left as it is.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('experiments')->where('key', 'ret1')->exists()) {
            return;
        }

        DB::table('experiments')->insert([
            'key' => 'ret1',
            'what' => 'Home with a "Today" line in the course card (B) or as today (A), past day 7',
            'hypothesis' => 'A "Today" line on Home brings more people back in their second week.',
            'arms' => json_encode([['arm' => 'a', 'weight' => 50], ['arm' => 'b', 'weight' => 50]]),
            'metric' => 'f_day14',
            'guardrails' => json_encode(['f_plus']),
            'audience' => json_encode(new stdClass),
            'excludes' => json_encode([]),
            'planned_per_arm' => 1500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Cache::forget('experiments.all');
    }

    public function down(): void
    {
        DB::table('experiments')->where('key', 'ret1')->whereNull('starts_on')->delete();
    }
};
