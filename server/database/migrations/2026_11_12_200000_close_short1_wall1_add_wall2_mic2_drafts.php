<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Hillel's "Stop both, decide" (2026-11-12, through the coordinator): short1 and wall1 close (no device draws them and
 * new devices are free of them); and two drafts for him to start in Admin > Experiments once the app's side lands:
 * wall2 (when the account wall comes) and mic2 (lesson 1's first screen). Safe to run again.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Production's registry only, as short1's start was: tests and dev databases keep theirs as their migrations
        // made them, so no test or local flow changes under them.
        if (! app()->isProduction()) {
            return;
        }

        foreach (['short1', 'wall1'] as $key) {
            $row = DB::table('experiments')->where('key', $key)->first();
            if ($row === null || $row->closed_at !== null) {
                continue;
            }
            DB::table('experiments')->where('key', $key)->update([
                // A running one ends yesterday, as the registry's stop does; a draft never ran.
                'ends_on' => $row->starts_on !== null && $row->ends_on === null ? now()->subDay()->toDateString() : $row->ends_on,
                'closed_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $drafts = [
            [
                'key' => 'wall2',
                'what' => 'When the account wall comes: before lesson 2, as today (A), or before lesson 4, after the first session\'s wrap (B)',
                'hypothesis' => 'Asking for an account after a whole first session gets more guests to lesson 3 without fewer accounts.',
                'metric' => 'f_lesson3',
                'guardrails' => ['f_account', 'f_day2'],
                'audience' => ['signedIn' => false, 'firstWeek' => true],
                'planned_per_arm' => 85,
            ],
            [
                'key' => 'mic2',
                'what' => 'Lesson 1\'s first screen: today\'s intro (A), or hear it first, with "Can\'t sing right now?" and saying no as a choice (B)',
                'hypothesis' => 'Hearing the lesson before the mic is asked for gets more first notes sung.',
                'metric' => 'f_note',
                'guardrails' => ['f_lesson1', 'f_day2'],
                'audience' => ['new' => true, 'firstWeek' => true],
                'planned_per_arm' => 210,
            ],
        ];
        foreach ($drafts as $draft) {
            if (DB::table('experiments')->where('key', $draft['key'])->exists()) {
                continue;
            }
            DB::table('experiments')->insert([
                'key' => $draft['key'],
                'what' => $draft['what'],
                'hypothesis' => $draft['hypothesis'],
                'arms' => json_encode([['arm' => 'a', 'weight' => 50], ['arm' => 'b', 'weight' => 50]]),
                'metric' => $draft['metric'],
                'guardrails' => json_encode($draft['guardrails']),
                'audience' => json_encode($draft['audience']),
                'excludes' => json_encode([]),
                'planned_per_arm' => $draft['planned_per_arm'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Cache::forget('experiments.all');
        Cache::forget('funnel.current');
    }

    public function down(): void
    {
        // Left in place: closing is the registry's own step, and a draft is removed in Admin.
    }
};
