<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Models\Experiment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Hillel's "Stop both, decide" (2026-11-12): short1 and wall1 close, wall2 and mic2 wait as drafts.
 */
final class ExperimentsRoundTwoTest extends TestCase
{
    use RefreshDatabase;

    public function test_short1_and_wall1_close_and_wall2_and_mic2_are_drafts(): void
    {
        DB::table('experiments')->whereIn('key', ['short1', 'wall1', 'wall2', 'mic2'])->delete();
        $row = static fn (string $key, ?string $starts): array => ['key' => $key, 'what' => $key, 'hypothesis' => 'h', 'arms' => json_encode([['arm' => 'a', 'weight' => 50], ['arm' => 'b', 'weight' => 50]]),
            'metric' => 'f_lesson3', 'guardrails' => '[]', 'audience' => '{}', 'excludes' => '[]', 'planned_per_arm' => 100, 'starts_on' => $starts, 'created_at' => now(), 'updated_at' => now()];
        DB::table('experiments')->insert([$row('short1', now()->subDays(9)->toDateString()), $row('wall1', null)]);

        $migration = require database_path('migrations/2026_11_12_200000_close_short1_wall1_add_wall2_mic2_drafts.php');
        $migration->up();
        $this->assertNull(Experiment::query()->where('key', 'short1')->value('closed_at'), 'not here: production only');
        $this->app['env'] = 'production';
        try {
            $migration->up();
            $migration->up();
        } finally {
            $this->app['env'] = 'testing';
        }

        $status = static fn (string $key): string => Experiment::query()->where('key', $key)->firstOrFail()->status();
        $this->assertSame([Experiment::CLOSED, Experiment::CLOSED, Experiment::DRAFT, Experiment::DRAFT], [$status('short1'), $status('wall1'), $status('wall2'), $status('mic2')]);
        $this->assertNotNull(Experiment::query()->where('key', 'short1')->value('ends_on'), 'a running one ends');
        $this->assertSame(['f_lesson1', 'f_day2'], json_decode((string) DB::table('experiments')->where('key', 'mic2')->value('guardrails'), true));
        $this->assertSame(1, DB::table('experiments')->where('key', 'wall2')->count(), 'safe to run again');
        // No device draws a closed or draft experiment.
        $ids = array_column((array) $this->getJson('/api/auth/config')->json('data.experiments'), 'id');
        $this->assertSame([], array_values(array_intersect($ids, ['short1', 'wall1', 'wall2', 'mic2'])));
    }

    public function test_lesson_ones_ways_round_a_first_try_count(): void
    {
        $kinds = ['l1_hear', 'l1_later_shown', 'l1_listen', 'l1_listen_heard', 'l1_later_link', 'l1_later_cal', 'inapp_handoff'];
        $this->postJson('/api/counts', ['events' => array_map(static fn (string $kind): array => ['kind' => $kind], [...$kinds, 'l1_made_up'])])->assertNoContent();

        $kept = DB::table('usage_counts')->pluck('kind')->all();
        sort($kept);
        sort($kinds);
        $this->assertSame($kinds, $kept);
    }
}
