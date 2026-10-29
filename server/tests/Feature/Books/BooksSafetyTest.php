<?php

declare(strict_types=1);

namespace Tests\Feature\Books;

use App\Actions\Books\Books;
use App\Actions\Books\Ledger;
use App\Actions\Templates\SyncOfficialTemplates;
use App\Models\CoachingPayment;
use App\Models\CoachOffering;
use App\Models\Engagement;
use App\Models\InvoiceNinjaConnection;
use App\Models\InvoiceNinjaRecord;
use App\Models\SocialPost;
use App\Models\User;
use App\Services\InvoiceNinja\FakeInvoiceNinja;
use App\Services\InvoiceNinja\InvoiceNinja;
use App\Services\InvoiceNinja\InvoiceNinjaError;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Coaching;
use Tests\TestCase;

/**
 * The books when things go wrong: lost answers, cut-off workers, refunds out of order, Stripe's
 * newer invoices, payments that don't complete (M10's independent review).
 */
final class BooksSafetyTest extends TestCase
{
    use Coaching;
    use RefreshDatabase;

    private FakeInvoiceNinja $ninja;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeConnect();
        $this->ninja = new FakeInvoiceNinja;
        $this->app->instance(InvoiceNinja::class, $this->ninja);
    }

    private function coachBooks(User $coach): InvoiceNinjaConnection
    {
        return InvoiceNinjaConnection::factory()->create(['user_id' => $coach->id, 'token' => 'in-coach-'.$coach->id, 'backfilled_at' => now()]);
    }

    private function engagement(User $coach, CoachOffering $offering, int $price = 2500): Engagement
    {
        $singer = User::factory()->create();

        return Engagement::factory()->create(['kind' => Engagement::REVIEW, 'singer_id' => $singer->id, 'coach_id' => $coach->id, 'offering_id' => $offering->id, 'status' => Engagement::REQUESTED, 'price_cents' => $price, 'fee_cents' => intdiv($price * 15, 100), 'refunded_cents' => 0, 'currency' => 'gbp', 'stripe_payment_intent' => null, 'stripe_charge' => null]);
    }

    public function test_a_payment_whose_answer_was_lost_is_found_not_made_twice_and_a_cut_off_row_is_taken_back(): void
    {
        Queue::fake();
        [$coach, $offering] = $this->coach();
        $connection = $this->coachBooks($coach);
        $engagement = $this->engagement($coach, $offering);
        $engagement->forceFill(['stripe_payment_intent' => 'pi_1', 'stripe_charge' => 'ch_1'])->save();
        $sale = InvoiceNinjaRecord::query()->where('kind', InvoiceNinjaRecord::SALE)->sole();
        $books = app(Books::class);

        $this->ninja->loseAnswerOfNext('payments');

        try {
            $books->record($sale);
            $this->fail('The lost answer should fail the try.');
        } catch (InvoiceNinjaError $e) {
            $this->assertSame(InvoiceNinjaError::UNREACHABLE, $e->reason);
        }

        $books->record($sale->refresh());
        $this->assertSame(InvoiceNinjaRecord::RECORDED, $sale->refresh()->status);
        $this->assertCount(1, $this->ninja->of($connection)['payments']);

        // A worker stopped mid-way: the row waits as `recording`, then is taken back.
        $fee = InvoiceNinjaRecord::query()->where('kind', InvoiceNinjaRecord::FEE)->sole();
        $fee->forceFill(['status' => InvoiceNinjaRecord::RECORDING])->save();
        $books->record($fee);
        $this->assertSame(InvoiceNinjaRecord::RECORDING, $fee->refresh()->status);
        $this->travel(Books::STALE_MINUTES + 1)->minutes();
        $this->assertSame(1, Books::sweep());
        $books->record($fee);
        $this->assertSame(InvoiceNinjaRecord::RECORDED, $fee->refresh()->status);
    }

    public function test_refunds_go_in_order_once_each_and_the_commission_comes_back_with_them(): void
    {
        Queue::fake();
        [$coach, $offering] = $this->coach();
        $connection = $this->coachBooks($coach);
        $engagement = $this->engagement($coach, $offering, 10000);
        $engagement->forceFill(['stripe_payment_intent' => 'pi_2', 'stripe_charge' => 'ch_2'])->save();
        $engagement->forceFill(['refunded_cents' => 3000])->save();
        $engagement->forceFill(['refunded_cents' => 5000])->save();
        $books = app(Books::class);
        [$sale, $first, $second] = [InvoiceNinjaRecord::query()->where('kind', 'sale')->sole(), ...InvoiceNinjaRecord::query()->where('kind', 'refund')->orderBy('id')->get()];

        // The second can't go before the first, nor either before the sale.
        $books->record($second);
        $this->assertSame('waiting', $second->refresh()->error);
        $books->record($sale);

        // The first's answer is lost after Invoice Ninja made it; its retry doesn't make it again.
        $this->ninja->loseAnswerOfNext('refunds');

        try {
            $books->record($first->refresh());
        } catch (InvoiceNinjaError $e) {
            $this->assertSame(InvoiceNinjaError::UNREACHABLE, $e->reason);
        }

        $books->record($first->refresh());
        $books->record($second->refresh());
        $payment = array_values($this->ninja->of($connection)['payments'])[0];
        $this->assertSame(5000, $payment['refunded']);
        $this->assertSame([InvoiceNinjaRecord::RECORDED, InvoiceNinjaRecord::RECORDED], [$first->refresh()->status, $second->refresh()->status]);

        foreach (InvoiceNinjaRecord::query()->where('kind', 'fee')->get() as $fee) {
            $books->record($fee);
        }

        $this->assertSame([1500, -450, -300], array_values(array_column($this->ninja->of($connection)['expenses'], 'cents')));
    }

    public function test_a_package_refunded_before_its_invoice_came_is_a_sale_and_its_refund(): void
    {
        [$coach, $package] = $this->coach(CoachOffering::PACKAGE);
        $singer = User::factory()->create();
        $engagement = Engagement::factory()->create(['kind' => Engagement::PACKAGE, 'singer_id' => $singer->id, 'coach_id' => $coach->id, 'offering_id' => $package->id, 'status' => Engagement::REFUNDED, 'price_cents' => 6000, 'fee_cents' => 900, 'refunded_cents' => 0, 'currency' => 'gbp']);
        $engagement->forceFill(['refunded_cents' => 6000])->save();
        $this->assertSame(0, CoachingPayment::query()->count());

        app(Ledger::class)->packageMonth(['id' => 'in_pkg', 'amount_paid' => 6000, 'status_transitions' => ['paid_at' => now()->getTimestamp()], 'lines' => ['data' => [['metadata' => ['kind' => 'coaching', 'engagement' => $engagement->sqid()]]]]]);

        $this->assertSame([6000, -6000], CoachingPayment::query()->orderBy('id')->pluck('cents')->all());
        $this->assertSame(0, (int) CoachingPayment::query()->sum('fee_cents'));
    }

    public function test_newer_stripe_invoices_are_matched_to_their_refunds_and_tax_has_a_line_of_its_own(): void
    {
        $platform = InvoiceNinjaConnection::factory()->platform()->create(['token' => 'in-platform-x']);
        $user = User::factory()->create(['stripe_id' => 'cus_new', 'email' => 'ana@example.com']);

        $this->stripeEvent('invoice.paid', ['id' => 'in_new', 'customer' => 'cus_new', 'amount_paid' => 4800, 'currency' => 'eur', 'total_taxes' => [['amount' => 800]], 'payments' => ['data' => [['payment' => ['type' => 'payment_intent', 'payment_intent' => 'pi_new']]]]])->assertOk();
        $this->stripeEvent('charge.refunded', ['id' => 'ch_new', 'payment_intent' => 'pi_new', 'amount_refunded' => 4800])->assertOk();

        $invoice = array_values($this->ninja->of($platform)['invoices'])[0];
        $this->assertSame([4000, 800], array_column($invoice['lines'], 'cents'));
        $this->assertSame(4800, array_values($this->ninja->of($platform)['payments'])[0]['refunded']);
        $this->assertNotNull($user);
    }

    public function test_plus_by_invoice_waits_for_completed_payments_covering_it(): void
    {
        $admin = $this->actingAsAdmin();
        $this->postJson('/api/admin/books', ['token' => 'in-platform-y'])->assertOk();
        $connection = InvoiceNinjaConnection::query()->where('owner_key', 'platform')->sole();
        $person = User::factory()->create(['email' => 'school@example.org']);
        $this->postJson('/api/admin/books/plus-invoices', ['personId' => $person->sqid(), 'amountCents' => 6000, 'currency' => 'gbp', 'months' => 6])->assertCreated();
        $record = InvoiceNinjaRecord::query()->where('kind', InvoiceNinjaRecord::PLUS_INVOICE)->sole();
        $hook = fn (string $payment) => $this->postJson('/webhooks/invoice-ninja/'.$connection->sqid(), ['id' => $payment], ['X-Webhook-Secret' => (string) $connection->webhook_secret])->assertOk();

        // Half paid, then the rest by a bank transfer still pending: not yet.
        $hook($this->ninja->pay($connection, (string) $record->in_invoice_id, 3000));
        $pending = $this->ninja->pay($connection, (string) $record->in_invoice_id, 3000, completed: false);
        $hook($pending);
        $this->assertNull($person->refresh()->plus_paid_until);
        $this->assertMatchesContract($this->deleteJson('/api/admin/books'), 'admin.books.delete', 409);

        // It clears; Invoice Ninja sends nothing more, but the hourly check sees it.
        $this->ninja->complete($connection, $pending);
        $this->artisan('app:books:sweep')->assertSuccessful();
        $this->assertTrue($person->refresh()->plus_paid_until?->between(now()->addMonths(6)->subMinute(), now()->addMonths(6)->addMinute()));
        $this->getJson('/api/admin/people/'.$person->sqid())->assertJsonPath('data.plusPaidUntil', $person->plus_paid_until?->utc()->toIso8601ZuluString());
        $this->assertNotNull($admin);
    }

    public function test_a_month_that_came_out_negative_is_taken_off_the_next(): void
    {
        InvoiceNinjaConnection::factory()->platform()->create(['token' => 'in-platform-z']);
        [$coach, $offering] = $this->coach();
        $october = Carbon::parse('2026-10-15 12:00');
        $sale = fn (Carbon $at, int $fee, string $kind = CoachingPayment::SALE) => CoachingPayment::factory()->create(['engagement_id' => null, 'coach_id' => $coach->id, 'kind' => $kind, 'cents' => $fee * 10, 'fee_cents' => $fee, 'currency' => 'gbp', 'paid_at' => $at]);
        $sale($october, 300);
        $sale($october->copy()->addMonth(), -300, CoachingPayment::REFUND);
        $sale($october->copy()->addMonths(2), 500);
        CoachingPayment::factory()->create(['engagement_id' => null, 'coach_id' => $coach->id, 'cents' => 9000, 'fee_cents' => 900, 'currency' => 'gbp', 'paid_at' => $october->copy()->subMonth(), 'backfilled' => true]);
        $books = app(Books::class);

        $books->commission($october);
        $books->commission($october->copy()->addMonth());
        $books->commission($october->copy()->addMonths(2));

        $this->assertSame([300, 200], InvoiceNinjaRecord::query()->where('kind', InvoiceNinjaRecord::COMMISSION)->orderBy('id')->pluck('cents')->all());
        $this->assertNotNull($offering);
    }

    public function test_a_new_song_is_proposed_once_when_first_reviewed_not_on_every_sync(): void
    {
        config(['services.bluesky' => ['handle' => 'stackapella.com', 'app_password' => 'pw', 'service' => 'https://bsky.social']]);
        $folder = storage_path('framework/testing/templates-'.uniqid());
        mkdir($folder, 0777, true);
        $grace = (array) json_decode((string) file_get_contents(base_path('../web/templates/amazing-grace.json')), true);
        config(['templates.folders' => [$folder]]);

        file_put_contents($folder.'/amazing-grace.json', (string) json_encode([...$grace, 'reviewed' => false]));
        app(SyncOfficialTemplates::class)->handle();
        $this->assertSame(0, SocialPost::query()->count());

        file_put_contents($folder.'/amazing-grace.json', (string) json_encode([...$grace, 'reviewed' => true]));
        app(SyncOfficialTemplates::class)->handle();
        $this->assertSame(1, SocialPost::query()->count());

        SocialPost::query()->delete();
        app(SyncOfficialTemplates::class)->handle();
        $this->assertSame(0, SocialPost::query()->count());
    }
}
