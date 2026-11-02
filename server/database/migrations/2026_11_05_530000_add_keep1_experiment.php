<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * keep1 (docs/account-nudges-plan.md): the first-week recap's keep row, as a draft in the registry,
 * never started here (the coordinator starts it in Admin > Experiments, where its details can be
 * edited first). Safe to run again: an existing keep1 is left as it is.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('experiments')->where('key', 'keep1')->exists()) {
            return;
        }

        DB::table('experiments')->insert([
            'key' => 'keep1',
            'what' => 'The first-week recap with a keep row (B) or as today (A)',
            'hypothesis' => 'A keep row on the first-week recap gets more people to make an account.',
            'arms' => json_encode([['arm' => 'a', 'weight' => 50], ['arm' => 'b', 'weight' => 50]]),
            'metric' => 'f_account',
            'guardrails' => json_encode(['f_day14']),
            'audience' => json_encode(['firstWeek' => true]),
            'excludes' => json_encode([]),
            'planned_per_arm' => 1500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Cache::forget('experiments.all');
    }

    public function down(): void
    {
        DB::table('experiments')->where('key', 'keep1')->whereNull('starts_on')->delete();
    }
};
