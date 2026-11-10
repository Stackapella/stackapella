<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * wall1 (the coordinator, 2026-11-08): the lesson-2 account wall with a button to sign-up (A) or with
 * Google and Apple on it (B), as a draft in the registry, never started here (Hillel starts it in
 * Admin > Experiments). The wall meets a guest in their first week, so its audience says so; starting it
 * is Hillel's OK to enrol them. Safe to run again: an existing wall1 is left as it is.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('experiments')->where('key', 'wall1')->exists()) {
            return;
        }

        DB::table('experiments')->insert([
            'key' => 'wall1',
            'what' => 'The lesson-2 account wall with Google and Apple on it (B) or a button to sign-up (A)',
            'hypothesis' => 'One tap to an account on the wall turns more guests who finish lesson 1 into accounts.',
            'arms' => json_encode([['arm' => 'a', 'weight' => 50], ['arm' => 'b', 'weight' => 50]]),
            'metric' => 'f_account',
            'guardrails' => json_encode(['f_day2']),
            'audience' => json_encode(['signedIn' => false, 'firstWeek' => true]),
            'excludes' => json_encode([]),
            'planned_per_arm' => 1500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Cache::forget('experiments.all');
    }

    public function down(): void
    {
        DB::table('experiments')->where('key', 'wall1')->whereNull('starts_on')->delete();
    }
};
