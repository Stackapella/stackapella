<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Models\CheckoutSession;
use App\Models\User;
use App\Support\Stats\ReportDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Admin's Choir view (admin.choir.show): the choir's anonymous counts, read as trends, a funnel and its reasons.
 */
final class ChoirInsightsTest extends TestCase
{
    use RefreshDatabase;

    private function counted(string $day, string $kind, int $n): void
    {
        DB::table('usage_counts')->insert(['day' => $day, 'kind' => $kind, 'audience' => 'account', 'country' => '', 'variant' => '', 'source' => '', 'count' => $n]);
    }

    public function test_the_choirs_numbers_this_period_and_the_one_before(): void
    {
        $today = ReportDay::now()->toDateString();
        $earlier = ReportDay::now()->subDays(3)->toDateString();
        $before = ReportDay::now()->subDays(40)->toDateString();
        $this->counted($today, 'choir_take_sent', 5);
        $this->counted($earlier, 'choir_take_sent', 4);
        $this->counted($today, 'own_choir_ready', 6);
        $this->counted($today, 'choir_clip_notes', 3);
        $this->counted($today, 'choir_clip_quiet', 7);
        $this->counted($today, 'choir_clip_ok_capture', 8);
        $this->counted($today, 'choir_gate_bass_low', 2);
        $this->counted($today, 'choir_tries_2', 1);
        $this->counted($today, 'choir_render_seconds', 900);
        $this->counted($today, 'choir_view_with_choir', 2);
        $this->counted($before, 'choir_take_sent', 11);
        $this->counted($today, 'not_choir', 99);
        $buyer = User::factory()->create();
        CheckoutSession::query()->forceCreate(['user_id' => $buyer->id, 'stripe_id' => 'cs_test_choir', 'moment' => 'choir_settings', 'interval' => 'year', 'currency' => 'usd', 'trial' => false, 'completed_at' => now()]);
        CheckoutSession::query()->forceCreate(['user_id' => $buyer->id, 'stripe_id' => 'cs_test_other', 'moment' => 'mp3', 'interval' => 'year', 'currency' => 'usd', 'trial' => false, 'completed_at' => now()]);

        $this->actingAs(User::factory()->create());
        $this->getJson('/api/admin/choir')->assertForbidden();

        $this->actingAsAdmin();
        $shown = $this->getJson('/api/admin/choir');
        $this->assertMatchesContract($shown, 'admin.choir.show', 200);
        $shown->assertJsonPath('data.period', '30d')
            ->assertJsonPath('data.funnel.2', ['step' => 'sent', 'n' => 9, 'before' => 11])
            ->assertJsonPath('data.reasons.0', ['reason' => 'quiet', 'n' => 7, 'before' => 0])
            ->assertJsonPath('data.reasons.1.reason', 'notes')
            ->assertJsonPath('data.songs.0', ['song' => 'capture', 'ok' => 8, 'failed' => 0])
            ->assertJsonPath('data.gates.0', ['part' => 'bass', 'voicing' => 'low', 'n' => 2])
            ->assertJsonPath('data.firstSuccess.1', ['tries' => '2', 'n' => 1])
            ->assertJsonPath('data.renderSeconds.n', 900)
            ->assertJsonPath('data.plusFromChoir', ['n' => 1, 'before' => 0]);
        $this->assertNotContains('not_choir', collect($shown->json('data.totals'))->pluck('kind')->all());
        $this->assertContains('choir_view_with_choir', collect($shown->json('data.totals'))->pluck('kind')->all());
        $this->assertCount(2, $shown->json('data.daily'));

        $this->getJson('/api/admin/choir?period=7d')->assertJsonPath('data.period', '7d')->assertJsonPath('data.funnel.2.before', 0);
        $this->getJson('/api/admin/choir?period=year')->assertUnprocessable();
    }
}
