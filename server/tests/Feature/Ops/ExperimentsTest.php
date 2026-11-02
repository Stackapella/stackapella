<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The experiments registry (docs/experiments-plan.md, slice 1): defined in Admin, a draft until
 * started, and the app learns the running ones from auth.config. onb1 moved in from config.
 */
final class ExperimentsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $with
     * @return array<string, mixed>
     */
    private function short1(array $with = []): array
    {
        return ['id' => 'short1', 'what' => 'A one-step lesson 1', 'hypothesis' => 'A one-step lesson 1 gets more people to finish lesson 3.', 'arms' => [['arm' => 'a', 'weight' => 50], ['arm' => 'b', 'weight' => 50]],
            'metric' => 'f_lesson3', 'guardrails' => ['f_note', 'f_account', 'f_plus'], 'audience' => ['new' => true, 'firstWeek' => true], 'excludes' => ['onb1'], 'plannedPerArm' => 1500, ...$with];
    }

    public function test_an_experiment_is_a_draft_then_runs_stops_is_decided_and_closed(): void
    {
        config(['counts.enabled' => true]);
        $this->actingAsAdmin();

        $list = $this->getJson('/api/admin/experiments');
        $this->assertMatchesContract($list, 'admin.experiments.list', 200);
        $this->assertSame('draft', collect((array) $list->json('data'))->firstWhere('id', 'onb1')['status']);
        // keep1 waits as a draft, never started by its migration.
        $keep1 = collect((array) $list->json('data'))->firstWhere('id', 'keep1');
        $this->assertSame('draft', $keep1['status']);
        // Guests only: its card shows on days 7 to 13, when no device is new any more.
        $this->assertSame(['signedIn' => false], $keep1['audience']);
        $this->assertSame(['f_day14', 'f_plus'], $keep1['guardrails']);
        // ret1 waits as a draft too: everyone the app asks for it (only past day 7), f_day14 its measure.
        $ret1 = collect((array) $list->json('data'))->firstWhere('id', 'ret1');
        $this->assertSame(['draft', 'f_day14', ['f_plus'], []], [$ret1['status'], $ret1['metric'], $ret1['guardrails'], $ret1['audience']]);

        $this->assertMatchesContract($this->postJson('/api/admin/experiments', $this->short1(['arms' => [['arm' => 'b', 'weight' => 50], ['arm' => 'a', 'weight' => 50]]])), 'admin.experiments.create', 422);
        $this->postJson('/api/admin/experiments', $this->short1(['metric' => 'made_up']))->assertJsonValidationErrors('metric');
        $made = $this->postJson('/api/admin/experiments', $this->short1());
        $this->assertMatchesContract($made, 'admin.experiments.create', 201);
        $made->assertJsonPath('data.status', 'draft');
        $this->getJson('/api/auth/config')->assertJsonPath('data.experiments', []);

        // Edited while a draft; started; then its fields are fixed.
        $this->patchJson('/api/admin/experiments/short1', ['plannedPerArm' => 1200])->assertOk()->assertJsonPath('data.plannedPerArm', 1200);
        $started = $this->patchJson('/api/admin/experiments/short1', ['action' => 'start']);
        $this->assertMatchesContract($started, 'admin.experiments.update', 200);
        $started->assertJsonPath('data.status', 'running');
        $this->assertMatchesContract($this->patchJson('/api/admin/experiments/short1', ['plannedPerArm' => 900]), 'admin.experiments.update', 409);
        $this->getJson('/api/auth/config')->assertJsonPath('data.experiments', [['id' => 'short1', 'arms' => [['arm' => 'a', 'weight' => 50], ['arm' => 'b', 'weight' => 50]], 'enrol' => true, 'audience' => ['new' => true, 'firstWeek' => true], 'excludes' => ['onb1']]])
            ->assertJsonPath('data.experiment', null);

        // Stopped: no one new joins, it's read a week on; then decided and closed.
        $this->assertMatchesContract($this->patchJson('/api/admin/experiments/short1', ['action' => 'decide', 'winner' => 'b']), 'admin.experiments.update', 409);
        $this->patchJson('/api/admin/experiments/short1', ['action' => 'stop'])->assertOk()->assertJsonPath('data.status', 'reading')->assertJsonPath('data.readFrom', now()->subDay()->addDays(7)->toDateString());
        $this->getJson('/api/auth/config')->assertJsonPath('data.experiments.0.enrol', false);
        $this->patchJson('/api/admin/experiments/short1', ['action' => 'decide', 'winner' => 'b'])->assertOk()->assertJsonPath('data.status', 'decided')->assertJsonPath('data.winner', 'b');
        $this->patchJson('/api/admin/experiments/short1', ['action' => 'close'])->assertOk()->assertJsonPath('data.status', 'closed');
        $this->getJson('/api/auth/config')->assertJsonPath('data.experiments', []);
        $this->assertMatchesContract($this->patchJson('/api/admin/experiments/nope', ['action' => 'start']), 'admin.experiments.update', 404);

        $this->assertSame(['experiment.created', 'experiment.edited', 'experiment.start', 'experiment.stop', 'experiment.decide', 'experiment.close'], AuditLog::query()->where('action', 'like', 'experiment.%')->orderBy('id')->pluck('action')->all());
    }

    public function test_each_arm_counts_its_own_steps_from_enrolment_apart_from_the_everyday_totals(): void
    {
        config(['counts.enabled' => true]);
        $admin = $this->actingAsAdmin();
        $this->postJson('/api/admin/experiments', $this->short1())->assertCreated();
        $this->patchJson('/api/admin/experiments/short1', ['action' => 'start'])->assertOk();
        auth()->forgetGuards();

        $send = fn (array $kinds, array $variants) => $this->postJson('/api/counts', ['events' => array_map(fn (string $k): array => ['kind' => $k], $kinds), 'variants' => $variants])->assertNoContent();
        // Two devices drawn into a, three into b; b's get further. An arm the registry doesn't know, or onb1's, isn't counted here.
        foreach ([['short1-a', ['exp_enrol', 'f_lesson3']], ['short1-a', ['exp_enrol']], ['short1-b', ['exp_enrol', 'f_lesson3']], ['short1-b', ['exp_enrol', 'f_lesson3']], ['short1-b', ['exp_enrol']]] as [$arm, $kinds]) {
            $send($kinds, [$arm, 'nope-z', 'onb1-a']);
        }
        $send(['f_lesson3', 'f_day7', 'f_plus'], []);

        $this->assertSame(0, (int) DB::table('experiment_counts')->whereIn('variant', ['nope-z', 'onb1-a'])->count());
        // The everyday totals count each device once, and never exp_enrol.
        $this->assertSame(4, (int) DB::table('usage_counts')->where('kind', 'f_lesson3')->sum('count'));
        $this->assertSame(0, (int) DB::table('usage_counts')->where('kind', 'exp_enrol')->count());
        $this->assertSame(1, (int) DB::table('usage_counts')->where('kind', 'f_plus')->sum('count'));

        $this->actingAsAdmin($admin);
        $report = $this->getJson('/api/admin/funnel?experiment=short1');
        $this->assertMatchesContract($report, 'admin.funnel.show', 200);
        $report->assertJsonPath('data.steps.0.kind', 'exp_enrol')->assertJsonPath('data.steps.0.arms.1.total', 3);
        $lesson3 = collect((array) $report->json('data.steps'))->firstWhere('kind', 'f_lesson3');
        $this->assertSame([0.5, 0.6667], [$lesson3['arms'][0]['rate'], $lesson3['arms'][1]['rate']]);
    }

    public function test_only_an_admin_in_sudo_changes_the_registry(): void
    {
        $this->actingAsAdmin(secondFactorPassed: false);
        $this->postJson('/api/admin/experiments', $this->short1())->assertForbidden();
        $this->actingAsEditor();
        $this->getJson('/api/admin/experiments')->assertForbidden();
        $this->actingAs(User::factory()->create()->fresh() ?? throw new \LogicException)->getJson('/api/admin/experiments')->assertForbidden();
    }
}
