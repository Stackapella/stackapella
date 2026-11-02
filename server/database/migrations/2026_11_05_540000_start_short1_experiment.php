<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The first experiment, short1 (docs/experiments.md), started by the deploy that runs this: Hillel's
 * "Yes, start it" (2026-10-31, through the coordinator), with Admin > Experiments not built yet. Safe
 * to run again: an existing short1 (started, stopped or closed in the registry) is left as it is.
 * Stopping it in the registry ends new enrolment; closing it takes it out of the app.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Production's state, not the schema: tests and dev databases keep an empty registry, so no
        // test or local flow is drawn into an arm by surprise.
        if (! app()->isProduction()) {
            return;
        }

        if (DB::table('experiments')->where('key', 'short1')->exists()) {
            return;
        }

        DB::table('experiments')->insert([
            'key' => 'short1',
            'what' => 'A one-step lesson 1',
            'hypothesis' => 'A one-step lesson 1 gets more people to finish lesson 3.',
            'arms' => json_encode([['arm' => 'a', 'weight' => 50], ['arm' => 'b', 'weight' => 50]]),
            'metric' => 'f_lesson3',
            'guardrails' => json_encode(['f_note', 'f_account', 'f_plus']),
            'audience' => json_encode(['new' => true, 'firstWeek' => true]),
            'excludes' => json_encode(['onb1']),
            'planned_per_arm' => 1500,
            'starts_on' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Cache::forget('experiments.all');
        Cache::forget('funnel.current');
    }

    public function down(): void
    {
        // Left in place: its counts belong to it, and stopping it is the registry's job.
    }
};
