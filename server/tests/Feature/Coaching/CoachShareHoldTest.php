<?php

declare(strict_types=1);

namespace Tests\Feature\Coaching;

use App\Actions\Coaching\Engagements;
use App\Models\CoachProfile;
use App\Models\Engagement;
use App\Models\ReviewItem;
use App\Models\User;
use App\Services\Billing\BillingGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Coaching;
use Tests\Support\FakeBillingGateway;
use Tests\TestCase;

/**
 * Hillel: "Hold 7 days, reverse on dispute" (2026-11-01): a coach's share of a review moves 7 days
 * after delivery (14 in their first 30 days), a dispute takes a paid share back, and a dispute won
 * pays the coach after all.
 */
final class CoachShareHoldTest extends TestCase
{
    use Coaching;
    use RefreshDatabase;

    private function delivered(User $coach, string $charge): Engagement
    {
        CoachProfile::factory()->create(['user_id' => $coach->id]);
        $engagement = Engagement::factory()->create(['coach_id' => $coach->id, 'status' => Engagement::DELIVERED, 'stripe_charge' => $charge, 'price_cents' => 2500, 'fee_cents' => 375]);
        ReviewItem::factory()->create(['engagement_id' => $engagement->id, 'status' => ReviewItem::DELIVERED, 'delivered_at' => now()]);

        return $engagement;
    }

    public function test_a_share_waits_seven_days_or_fourteen_for_a_new_coach(): void
    {
        $this->fakeConnect();
        $this->delivered(User::factory()->create(['coach_active_at' => now()->subYear()]), 'ch_seasoned');
        $this->delivered(User::factory()->create(['coach_active_at' => now()->subDays(3)]), 'ch_new');

        $this->travel(6)->days();
        $this->artisan('app:coaching:deadlines')->assertSuccessful();
        $this->assertSame([], $this->connect->transfers);

        $this->travel(2)->days();
        $this->artisan('app:coaching:deadlines')->assertSuccessful();
        $this->assertSame(['ch_seasoned'], array_column($this->connect->transfers, 'charge'));

        $this->travel(7)->days();
        $this->artisan('app:coaching:deadlines')->assertSuccessful();
        $this->assertSame(['ch_seasoned', 'ch_new'], array_column($this->connect->transfers, 'charge'));
    }

    public function test_a_dispute_takes_a_paid_share_back_and_a_win_pays_it_again(): void
    {
        $this->fakeConnect();
        $engagement = $this->delivered(User::factory()->create(['coach_active_at' => now()->subYear()]), 'ch_disputed');
        $this->travel(8)->days();
        $this->artisan('app:coaching:deadlines')->assertSuccessful();
        $transfer = (string) $engagement->refresh()->stripe_transfer;
        $this->assertNotSame('', $transfer);

        app(Engagements::class)->disputed('ch_disputed');
        $this->assertSame([[$transfer, 2125]], $this->connect->reversals);
        $this->assertNull($engagement->refresh()->stripe_transfer);
        $this->assertNotNull($engagement->transfer_reversed_at);
        $this->artisan('app:coaching:deadlines')->assertSuccessful();
        $this->assertCount(1, $this->connect->transfers);

        // Won: paid again, as a new transfer.
        app(Engagements::class)->disputeWon('ch_disputed');
        $this->artisan('app:coaching:deadlines')->assertSuccessful();
        $this->assertCount(2, $this->connect->transfers);
        $this->assertNotNull($engagement->refresh()->stripe_transfer);
    }

    public function test_only_an_open_dispute_holds_a_share_and_a_closed_inquiry_clears_it(): void
    {
        $this->fakeConnect();
        $this->app->instance(BillingGateway::class, new FakeBillingGateway);
        $engagement = $this->delivered(User::factory()->create(['coach_active_at' => now()->subYear()]), 'ch_inquiry');

        // A late update to a dispute already won doesn't hold the share again.
        $this->stripeEvent('charge.dispute.updated', ['id' => 'dp_1', 'charge' => 'ch_inquiry', 'amount' => 2500, 'status' => 'won'])->assertOk();
        $this->assertNull($engagement->refresh()->disputed_at);

        // An inquiry: held while open, cleared when it closes without a chargeback.
        $this->stripeEvent('charge.dispute.created', ['id' => 'dp_2', 'charge' => 'ch_inquiry', 'amount' => 2500, 'status' => 'warning_needs_response'])->assertOk();
        $this->assertNotNull($engagement->refresh()->disputed_at);
        $this->stripeEvent('charge.dispute.closed', ['id' => 'dp_2', 'charge' => 'ch_inquiry', 'amount' => 2500, 'status' => 'warning_closed'])->assertOk();
        $this->assertNull($engagement->refresh()->disputed_at);
    }

    public function test_a_lessons_share_taken_back_is_paid_again_after_a_win_however_long_ago_it_ended(): void
    {
        $this->fakeConnect();
        $coach = User::factory()->create(['coach_active_at' => now()->subYear()]);
        CoachProfile::factory()->create(['user_id' => $coach->id]);
        $engagement = Engagement::factory()->create(['kind' => Engagement::LESSON, 'coach_id' => $coach->id, 'status' => Engagement::DELIVERED, 'stripe_charge' => 'ch_lesson', 'stripe_transfer' => 'tr_lesson', 'price_cents' => 2500, 'fee_cents' => 375, 'ended_at' => now()->subDays(40)]);

        app(Engagements::class)->disputed('ch_lesson');
        $this->assertNotNull($engagement->refresh()->transfer_reversed_at);
        app(Engagements::class)->disputeWon('ch_lesson');
        $this->artisan('app:coaching:deadlines')->assertSuccessful();

        $this->assertSame(['ch_lesson'], array_column($this->connect->transfers, 'charge'));
    }
}
