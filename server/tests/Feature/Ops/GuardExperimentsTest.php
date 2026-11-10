<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Actions\Counts\Funnel;
use App\Models\AuditLog;
use App\Models\Experiment;
use App\Models\User;
use App\Notifications\Admin\OpsAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The experiments' one early stop: a running test whose B is worse on a guardrail beyond chance
 * at 99% is closed, audited, and the admins are told.
 */
final class GuardExperimentsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, array{0: int, 1: int}>  $counts  kind => [a, b]
     */
    private function running(string $key, array $counts): Experiment
    {
        $experiment = Experiment::factory()->create(['key' => $key, 'guardrails' => ['f_account'], 'starts_on' => now()->subDays(5)->toDateString()]);

        foreach ($counts as $kind => [$a, $b]) {
            foreach (['a' => $a, 'b' => $b] as $arm => $n) {
                DB::table('experiment_counts')->insert(['day' => now()->subDay()->toDateString(), 'variant' => $key.'-'.$arm, 'kind' => $kind, 'audience' => 'guest', 'count' => $n]);
            }
        }

        return $experiment;
    }

    public function test_a_test_worse_on_a_guardrail_at_99_percent_is_closed_and_the_admins_told(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $bad = $this->running('bad', ['exp_enrol' => [400, 400], 'f_account' => [120, 60]]);
        $close = $this->running('close', ['exp_enrol' => [400, 400], 'f_account' => [120, 110]]);
        $tiny = $this->running('tiny', ['exp_enrol' => [30, 30], 'f_account' => [20, 2]]);

        $this->artisan('app:experiments:guard')->expectsOutputToContain('Closed 1')->assertSuccessful();

        $this->assertSame('closed', $bad->refresh()->status());
        // The app no longer offers it, so devices in B go back to the current app.
        config(['counts.enabled' => true]);
        $this->assertSame(['close', 'tiny'], array_column(app(Funnel::class)->running(), 'id'));
        $this->assertSame('running', $close->refresh()->status());
        $this->assertSame('running', $tiny->refresh()->status());
        $this->assertSame(1, AuditLog::query()->where('action', 'experiment.guardrail_stop')->count());
        Notification::assertSentTo($admin, OpsAlert::class, fn (OpsAlert $alert): bool => str_contains($alert->subject, 'bad'));
    }

    public function test_wall1_and_ret1_as_their_migrations_made_them_are_guarded_on_their_own_guardrails(): void
    {
        // Started by Hillel on 2026-11-08 (Admin > Experiments): the registry's own rows, each worse in B
        // on its guardrail (wall1: back on day 2; ret1: Plus started), enrolment counted from exp_enrol.
        Notification::fake();
        User::factory()->admin()->create();
        $started = [];

        foreach (['wall1' => 'f_day2', 'ret1' => 'f_plus'] as $key => $guardrail) {
            $experiment = Experiment::query()->where('key', $key)->firstOrFail();
            $this->assertSame([$guardrail], $experiment->guardrails);
            $experiment->forceFill(['starts_on' => now()->subDays(5)->toDateString()])->save();
            $started[$key] = $experiment;

            foreach (['exp_enrol' => [400, 400], $guardrail => [120, 60]] as $kind => [$a, $b]) {
                foreach (['a' => $a, 'b' => $b] as $arm => $n) {
                    DB::table('experiment_counts')->insert(['day' => now()->subDay()->toDateString(), 'variant' => $key.'-'.$arm, 'kind' => $kind, 'audience' => 'guest', 'count' => $n]);
                }
            }
        }

        $this->artisan('app:experiments:guard')->expectsOutputToContain('Closed 2')->assertSuccessful();
        $this->assertSame(['closed', 'closed'], [$started['wall1']->refresh()->status(), $started['ret1']->refresh()->status()]);
    }
}
