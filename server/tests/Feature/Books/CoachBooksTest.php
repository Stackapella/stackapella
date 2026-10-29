<?php

declare(strict_types=1);

namespace Tests\Feature\Books;

use App\Actions\Books\Books;
use App\Models\CoachingPayment;
use App\Models\CoachOffering;
use App\Models\Engagement;
use App\Models\InvoiceNinjaConnection;
use App\Models\InvoiceNinjaRecord;
use App\Models\User;
use App\Services\InvoiceNinja\FakeInvoiceNinja;
use App\Services\InvoiceNinja\HttpInvoiceNinja;
use App\Services\InvoiceNinja\InvoiceNinja;
use App\Services\InvoiceNinja\InvoiceNinjaError;
use App\Support\Http\PublicAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Coaching;
use Tests\TestCase;

/**
 * A coach's books in Invoice Ninja (contract/api.md, "Books, posts and signing in with Event
 * Schedule (M10)").
 */
final class CoachBooksTest extends TestCase
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

    protected function tearDown(): void
    {
        PublicAddress::$resolver = null;

        parent::tearDown();
    }

    /**
     * A singer buys a review from the coach and pays: [singer, engagement].
     *
     * @return array{0: User, 1: Engagement}
     */
    private function paidReview(User $coach, string $offering): array
    {
        [$singer, $take] = $this->singerWithTake();
        $this->as($singer);
        $id = (string) $this->postJson('/api/engagements', ['offeringId' => $offering, 'takeId' => $take, 'question' => 'My high notes.', 'adult' => true])->assertCreated()->json('data.id');
        $this->paid((string) Engagement::query()->whereSqid($id)->value('stripe_checkout_id'));

        return [$singer, Engagement::query()->whereSqid($id)->firstOrFail()];
    }

    public function test_a_coach_connects_and_each_sale_its_fee_and_its_refund_are_recorded_once(): void
    {
        [$coach, $offering] = $this->coach();
        $this->as($coach);
        $this->assertMatchesContract($this->getJson('/api/coach/invoice-ninja'), 'invoiceNinja.connection.show', 200);
        $this->assertMatchesContract($this->postJson('/api/coach/invoice-ninja', ['token' => 'wrong-token-123']), 'invoiceNinja.connect', 422);

        $connected = $this->postJson('/api/coach/invoice-ninja', ['token' => 'in-coach-token-1']);
        $this->assertMatchesContract($connected, 'invoiceNinja.connect', 200);
        $connected->assertJsonPath('data.companyName', 'Sarah\'s Studio')->assertJsonPath('data.sendEmail', false);
        $this->getJson('/api/coaches/'.$coach->handle)->assertJsonPath('data.keepsBooks', true);

        [$singer, $engagement] = $this->paidReview($coach, $offering->sqid());
        $connection = InvoiceNinjaConnection::query()->firstOrFail();
        $books = $this->ninja->of($connection);
        $this->assertCount(1, $books['clients']);
        $this->assertSame($singer->email, array_values($books['clients'])[0]['email']);
        $invoice = array_values($books['invoices'])[0];
        $this->assertSame('sale:'.$engagement->id, $invoice['poNumber']);
        $this->assertFalse($invoice['emailed']);
        $this->assertSame(2500, array_values($books['payments'])[0]['cents']);
        $this->assertSame([375], array_column($books['expenses'], 'cents'));
        $this->assertSame(['Stackapella'], array_values($books['vendors']));

        // Declined: refunded in full, and recorded as a refund on that payment.
        $this->as($coach);
        $this->postJson('/api/engagements/'.$engagement->sqid().'/decline', ['reason' => 'Booked up.'])->assertOk();
        $this->assertSame(2500, array_values($this->ninja->of($connection)['payments'])[0]['refunded']);
        $this->assertSame(2, CoachingPayment::query()->count());

        $shown = $this->getJson('/api/coach/invoice-ninja');
        $this->assertMatchesContract($shown, 'invoiceNinja.connection.show', 200);
        $shown->assertJsonPath('data.recorded', 4)->assertJsonPath('data.notRecorded', 0);
        // The commission came back with the refund, as a negative expense.
        $this->assertSame([375, -375], array_values(array_column($this->ninja->of($connection)['expenses'], 'cents')));

        $updated = $this->patchJson('/api/coach/invoice-ninja', ['sendEmail' => true]);
        $this->assertMatchesContract($updated, 'invoiceNinja.connection.update', 200);
        $this->assertMatchesContract($this->patchJson('/api/coach/invoice-ninja', []), 'invoiceNinja.connection.update', 422);
        [, $emailed] = $this->paidReview($coach, $offering->sqid());
        $this->assertTrue(collect($this->ninja->of($connection)['invoices'])->firstWhere('poNumber', 'sale:'.$emailed->id)['emailed'] ?? false);
    }

    public function test_a_lost_answer_is_found_again_and_a_refused_token_pauses_until_reconnecting(): void
    {
        Queue::fake();
        [$coach, $offering] = $this->coach();
        $this->as($coach);
        $this->postJson('/api/coach/invoice-ninja', ['token' => 'in-coach-token-1'])->assertOk();
        [, $engagement] = $this->paidReview($coach, $offering->sqid());
        $connection = InvoiceNinjaConnection::query()->firstOrFail();
        $sale = InvoiceNinjaRecord::query()->where('kind', InvoiceNinjaRecord::SALE)->firstOrFail();

        // The client is made, but its answer never arrives: the retry finds it, never a second.
        $this->ninja->loseAnswerOfNext();
        $books = app(Books::class);

        try {
            $books->record($sale);
        } catch (InvoiceNinjaError $e) {
            $this->assertSame(InvoiceNinjaError::UNREACHABLE, $e->reason);
        }

        $books->record($sale->refresh());
        $this->assertSame(InvoiceNinjaRecord::RECORDED, $sale->refresh()->status);
        $this->assertCount(1, $this->ninja->of($connection)['clients']);
        $this->assertCount(1, $this->ninja->of($connection)['invoices']);
        $this->assertCount(1, $this->ninja->of($connection)['payments']);

        // A refused token: the fee waits, the connection asks to reconnect.
        $fee = InvoiceNinjaRecord::query()->where('kind', InvoiceNinjaRecord::FEE)->firstOrFail();
        $this->ninja->failNext(InvoiceNinjaError::BAD_TOKEN);
        $books->record($fee);
        $this->assertSame([InvoiceNinjaRecord::FAILED, 'bad_token'], [$fee->refresh()->status, $fee->error]);
        $this->assertSame(InvoiceNinjaConnection::NEEDS_RECONNECT, $connection->refresh()->status);

        $this->as($coach);
        $list = $this->getJson('/api/coach/invoice-ninja/records');
        $this->assertMatchesContract($list, 'invoiceNinja.records.list', 200);
        $list->assertJsonPath('data.0.kind', 'fee')->assertJsonPath('data.0.problem', 'Invoice Ninja didn\'t accept the token. Connect it again.');
        $this->assertMatchesContract($this->getJson('/api/coach/invoice-ninja/records?status=nope'), 'invoiceNinja.records.list', 422);
        $this->assertMatchesContract($this->postJson('/api/coach/invoice-ninja/records/'.$fee->sqid().'/retry'), 'invoiceNinja.records.retry', 409);

        // Reconnected: what waited goes again.
        $this->postJson('/api/coach/invoice-ninja', ['token' => 'in-coach-token-1'])->assertOk()->assertJsonPath('data.status', 'active');
        $this->assertSame(InvoiceNinjaRecord::PENDING, $fee->refresh()->status);
        $books->record($fee);
        $this->assertSame(InvoiceNinjaRecord::RECORDED, $fee->refresh()->status);
        $this->assertMatchesContract($this->postJson('/api/coach/invoice-ninja/records/'.$fee->sqid().'/retry'), 'invoiceNinja.records.retry', 409);
        $this->assertSame('record_state', $this->postJson('/api/coach/invoice-ninja/records/'.$fee->sqid().'/retry')->json('code'));

        $this->ninja->failNext(InvoiceNinjaError::UNREACHABLE);
        $other = InvoiceNinjaRecord::factory()->create(['connection_id' => $connection->id, 'status' => InvoiceNinjaRecord::FAILED, 'error' => 'unreachable']);
        $retried = $this->postJson('/api/coach/invoice-ninja/records/'.$other->sqid().'/retry');
        $this->assertMatchesContract($retried, 'invoiceNinja.records.retry', 200);
        $this->assertMatchesContract($this->postJson('/api/coach/invoice-ninja/records/nope/retry'), 'invoiceNinja.records.retry', 404);
        $this->assertNotNull($engagement);
    }

    public function test_connecting_records_the_last_12_months_once_and_disconnecting_leaves_them(): void
    {
        [$coach, $offering] = $this->coach();
        $this->paidReview($coach, $offering->sqid());
        $old = Engagement::factory()->create(['kind' => Engagement::REVIEW, 'coach_id' => $coach->id, 'offering_id' => $offering->id, 'status' => Engagement::DELIVERED, 'price_cents' => 2000, 'fee_cents' => 300, 'currency' => 'gbp', 'stripe_payment_intent' => 'pi_old', 'stripe_charge' => 'ch_old', 'created_at' => now()->subMonths(13)]);
        $this->assertNotNull($old);
        $this->as($coach);

        $this->postJson('/api/coach/invoice-ninja', ['token' => 'in-coach-token-2'])->assertOk();
        $connection = InvoiceNinjaConnection::query()->firstOrFail();
        $this->assertNotNull($connection->backfilled_at);
        $this->assertCount(1, $this->ninja->of($connection)['invoices']);

        // Again: nothing twice.
        $this->postJson('/api/coach/invoice-ninja', ['token' => 'in-coach-token-2'])->assertOk();
        $this->assertCount(1, $this->ninja->of($connection)['invoices']);

        $this->assertMatchesContract($this->deleteJson('/api/coach/invoice-ninja'), 'invoiceNinja.connection.delete', 204);
        $this->assertMatchesContract($this->deleteJson('/api/coach/invoice-ninja'), 'invoiceNinja.connection.delete', 404);
        $this->assertMatchesContract($this->patchJson('/api/coach/invoice-ninja', ['sendEmail' => true]), 'invoiceNinja.connection.update', 404);
        $this->assertMatchesContract($this->getJson('/api/coach/invoice-ninja/records'), 'invoiceNinja.records.list', 404);
        $this->assertMatchesContract($this->postJson('/api/coach/invoice-ninja/records/x/retry'), 'invoiceNinja.records.retry', 404);
        $this->assertCount(1, $this->ninja->of($connection)['invoices']);
    }

    public function test_a_package_month_is_recorded_from_its_invoice_even_before_its_checkout(): void
    {
        [$coach, $package] = $this->coach(CoachOffering::PACKAGE);
        $this->as($coach);
        $this->postJson('/api/coach/invoice-ninja', ['token' => 'in-coach-token-3'])->assertOk();
        $singer = $this->as(User::factory()->create());
        $id = (string) $this->postJson('/api/engagements', ['offeringId' => $package->sqid(), 'adult' => true])->assertCreated()->json('data.id');
        $invoice = ['id' => 'in_month1', 'amount_paid' => 2500, 'charge' => 'ch_month1', 'subscription' => 'sub_x', 'lines' => ['data' => [['period' => ['start' => now()->getTimestamp(), 'end' => now()->addMonth()->getTimestamp()], 'metadata' => ['kind' => 'coaching', 'engagement' => $id]]]]];

        $this->stripeEvent('invoice.paid', $invoice)->assertOk();
        $this->stripeEvent('invoice.paid', $invoice)->assertOk();

        $this->assertSame(1, CoachingPayment::query()->where('engagement_id', Engagement::query()->whereSqid($id)->value('id'))->count());
        $this->assertSame(['sale:'.Engagement::query()->whereSqid($id)->value('id').':in_month1'], array_column($this->ninja->of(InvoiceNinjaConnection::query()->firstOrFail())['invoices'], 'poNumber'));
        $this->assertNotNull($singer);
    }

    public function test_only_coaches_connect_and_a_selfhosted_address_must_be_public(): void
    {
        $this->as(User::factory()->create());
        $this->assertMatchesContract($this->getJson('/api/coach/invoice-ninja'), 'invoiceNinja.connection.show', 404);
        $this->assertMatchesContract($this->postJson('/api/coach/invoice-ninja', ['token' => 'in-coach-token-1']), 'invoiceNinja.connect', 404);

        [$coach] = $this->coach();
        $this->as($coach);
        PublicAddress::$resolver = fn (string $host): array => $host === 'books.example.org' ? ['93.184.216.34'] : ['10.0.0.9'];
        $this->postJson('/api/coach/invoice-ninja', ['token' => 'in-coach-token-1', 'url' => 'https://inside.example.org'])->assertStatus(422)->assertJsonValidationErrors('url');
        $this->postJson('/api/coach/invoice-ninja', ['token' => 'in-coach-token-1', 'url' => 'books.example.org/api/v1'])->assertOk()->assertJsonPath('data.url', 'books.example.org/api/v1');

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/coach/invoice-ninja', ['token' => 'in-coach-token-1']);
        }

        $this->assertMatchesContract($this->postJson('/api/coach/invoice-ninja', ['token' => 'in-coach-token-1']), 'invoiceNinja.connect', 429);
    }

    public function test_the_api_address_is_built_by_suffix_never_by_trimming_characters(): void
    {
        $this->assertSame('https://invoicing.co/api/v1/', HttpInvoiceNinja::normalizeApiUrl(null));
        $this->assertSame('https://books.example.ai/api/v1/', HttpInvoiceNinja::normalizeApiUrl('books.example.ai'));
        $this->assertSame('https://books.example.io/ninja/api/v1/', HttpInvoiceNinja::normalizeApiUrl('https://books.example.io/ninja/api/v1/'));
        $this->assertSame('https://books.example.v1:8443/api/v1/', HttpInvoiceNinja::normalizeApiUrl('https://books.example.v1:8443'));
        $this->assertFalse(HttpInvoiceNinja::normalizeApiUrl('ftp://books.example.org'));
        $this->assertFalse(HttpInvoiceNinja::normalizeApiUrl('https://user@books.example.org'));
    }
}
