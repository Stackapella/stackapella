<?php

declare(strict_types=1);

namespace Tests\Feature\Books;

use App\Models\Engagement;
use App\Models\InvoiceNinjaConnection;
use App\Models\InvoiceNinjaRecord;
use App\Models\User;
use App\Services\InvoiceNinja\FakeInvoiceNinja;
use App\Services\InvoiceNinja\InvoiceNinja;
use App\Support\Usage\Plans;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\Coaching;
use Tests\TestCase;

/**
 * Stackapella's own books, our monthly commission, and Plus paid by invoice (contract/api.md,
 * "Books, posts and signing in with Event Schedule (M10)").
 */
final class PlatformBooksTest extends TestCase
{
    use Coaching;
    use RefreshDatabase;

    private FakeInvoiceNinja $ninja;

    private ?User $admin = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeConnect();
        $this->ninja = new FakeInvoiceNinja;
        $this->app->instance(InvoiceNinja::class, $this->ninja);
    }

    private function platform(): InvoiceNinjaConnection
    {
        $this->admin = $this->actingAsAdmin($this->admin);
        $made = $this->postJson('/api/admin/books', ['token' => 'in-platform-token']);
        $this->assertMatchesContract($made, 'admin.books.connect', 200);
        $made->assertJsonPath('data.companyName', 'Stackapella')->assertJsonPath('data.paymentsWebhook', true);

        return InvoiceNinjaConnection::query()->where('owner_key', InvoiceNinjaConnection::PLATFORM)->firstOrFail();
    }

    public function test_plus_payments_and_their_refunds_go_in_stackapellas_books(): void
    {
        $this->admin = $this->actingAsAdmin();
        $this->assertMatchesContract($this->getJson('/api/admin/books'), 'admin.books.show', 200);
        $this->assertMatchesContract($this->postJson('/api/admin/books', ['token' => 'in-platform-token', 'url' => 'http://10.0.0.1']), 'admin.books.connect', 422);
        $connection = $this->platform();
        $this->assertMatchesContract($this->getJson('/api/admin/books'), 'admin.books.show', 200);

        $user = User::factory()->create(['stripe_id' => 'cus_plus_1', 'email' => 'ana@example.com']);
        $this->stripeEvent('invoice.paid', ['id' => 'in_plus_1', 'customer' => 'cus_plus_1', 'amount_paid' => 3999, 'currency' => 'gbp', 'charge' => 'ch_plus_1'])->assertOk();
        $this->stripeEvent('invoice.paid', ['id' => 'in_plus_1', 'customer' => 'cus_plus_1', 'amount_paid' => 3999, 'currency' => 'gbp', 'charge' => 'ch_plus_1'])->assertOk();
        $books = $this->ninja->of($connection);
        $this->assertSame(['plus:in_plus_1'], array_column($books['invoices'], 'poNumber'));
        $this->assertSame([3999], array_column($books['payments'], 'cents'));

        $this->stripeEvent('charge.refunded', ['id' => 'ch_plus_1', 'amount_refunded' => 1000])->assertOk();
        $this->stripeEvent('charge.refunded', ['id' => 'ch_plus_1', 'amount_refunded' => 1000])->assertOk();
        $this->stripeEvent('charge.refunded', ['id' => 'ch_plus_1', 'amount_refunded' => 3999])->assertOk();
        $this->assertSame(3999, array_values($this->ninja->of($connection)['payments'])[0]['refunded']);

        $list = $this->getJson('/api/admin/books/records?status=recorded');
        $this->assertMatchesContract($list, 'admin.books.records.list', 200);
        $list->assertJsonCount(3, 'data');
        $this->assertMatchesContract($this->postJson('/api/admin/books/records/'.InvoiceNinjaRecord::query()->firstOrFail()->sqid().'/retry'), 'admin.books.records.retry', 409);
        $failed = InvoiceNinjaRecord::factory()->create(['connection_id' => $connection->id, 'status' => InvoiceNinjaRecord::FAILED]);
        $this->assertMatchesContract($this->postJson('/api/admin/books/records/'.$failed->sqid().'/retry'), 'admin.books.records.retry', 200);
        $this->assertMatchesContract($this->postJson('/api/admin/books/records/nope/retry'), 'admin.books.records.retry', 404);
        $this->assertNotNull($user);
    }

    public function test_on_the_first_each_coach_gets_one_invoice_for_last_months_commission(): void
    {
        $connection = $this->platform();
        [$coach, $offering] = $this->coach();
        [$singer, $take] = $this->singerWithTake();
        $this->as($singer);

        foreach ([1, 2] as $_) {
            $id = (string) $this->postJson('/api/engagements', ['offeringId' => $offering->sqid(), 'takeId' => $take, 'adult' => true])->assertCreated()->json('data.id');
            $this->paid((string) Engagement::query()->whereSqid($id)->value('stripe_checkout_id'));
        }

        // One refunded in full: its commission comes off.
        $this->as($coach);
        $this->postJson('/api/engagements/'.$id.'/decline')->assertOk();
        $this->actingAsAdmin($this->admin);

        $this->travelTo(Carbon::now()->addMonthNoOverflow()->startOfMonth()->setTime(6, 10));
        $this->artisan('app:books:commission')->assertSuccessful();
        $this->artisan('app:books:commission')->assertSuccessful();

        $commission = InvoiceNinjaRecord::query()->where('kind', InvoiceNinjaRecord::COMMISSION)->sole();
        $this->assertSame(375, $commission->cents);
        $this->assertSame(InvoiceNinjaRecord::RECORDED, $commission->status);
        $invoice = array_values(array_filter($this->ninja->of($connection)['invoices'], fn (array $i): bool => str_starts_with($i['poNumber'], 'commission:')))[0];
        $this->assertCount(3, $invoice['lines']);
        $this->assertSame([375, 375, -375], array_column($invoice['lines'], 'cents'));
    }

    public function test_plus_paid_by_invoice_starts_when_invoice_ninja_says_it_was_paid(): void
    {
        $this->admin = $this->actingAsAdmin();
        $person = User::factory()->create(['email' => 'choir@example.org']);
        $invoice = ['personId' => $person->sqid(), 'amountCents' => 6000, 'currency' => 'gbp', 'months' => 12];
        $this->assertMatchesContract($this->postJson('/api/admin/books/plus-invoices', $invoice), 'admin.books.plusInvoices.create', 409);

        $connection = $this->platform();
        $this->assertMatchesContract($this->postJson('/api/admin/books/plus-invoices', [...$invoice, 'months' => 99]), 'admin.books.plusInvoices.create', 422);
        $this->assertMatchesContract($this->postJson('/api/admin/books/plus-invoices', [...$invoice, 'personId' => 'nope']), 'admin.books.plusInvoices.create', 404);
        $noEmail = User::factory()->create(['email' => null]);
        $this->assertMatchesContract($this->postJson('/api/admin/books/plus-invoices', [...$invoice, 'personId' => $noEmail->sqid()]), 'admin.books.plusInvoices.create', 409);

        $made = $this->postJson('/api/admin/books/plus-invoices', $invoice);
        $this->assertMatchesContract($made, 'admin.books.plusInvoices.create', 201);
        $record = InvoiceNinjaRecord::query()->where('kind', InvoiceNinjaRecord::PLUS_INVOICE)->sole();
        $this->assertSame(InvoiceNinjaRecord::SENT, $record->status);
        $this->assertTrue($this->ninja->of($connection)['invoices'][(string) $record->in_invoice_id]['emailed']);
        $this->assertFalse(app(Plans::class)->isPlus($person->fresh() ?? $person));

        // Paid on Invoice Ninja's portal; its webhook names the payment, which is read back.
        $payment = $this->ninja->pay($connection, (string) $record->in_invoice_id, 6000);
        $this->postJson('/webhooks/invoice-ninja/'.$connection->sqid(), ['id' => $payment], ['X-Webhook-Secret' => 'wrong'])->assertNotFound();
        $this->postJson('/webhooks/invoice-ninja/'.$connection->sqid(), ['id' => 'Pa-made-up'], ['X-Webhook-Secret' => (string) $connection->webhook_secret])->assertOk();
        $this->assertSame(InvoiceNinjaRecord::SENT, $record->refresh()->status);

        $this->postJson('/webhooks/invoice-ninja/'.$connection->sqid(), ['id' => $payment], ['X-Webhook-Secret' => (string) $connection->webhook_secret])->assertOk();
        $this->postJson('/webhooks/invoice-ninja/'.$connection->sqid(), ['id' => $payment], ['X-Webhook-Secret' => (string) $connection->webhook_secret])->assertOk();

        $person->refresh();
        $this->assertSame(InvoiceNinjaRecord::PAID, $record->refresh()->status);
        $this->assertTrue($person->plus_paid_until?->between(now()->addMonths(12)->subMinute(), now()->addMonths(12)->addMinute()));
        $this->assertTrue(app(Plans::class)->isPlus(User::query()->findOrFail($person->id)));

        $this->assertMatchesContract($this->deleteJson('/api/admin/books'), 'admin.books.delete', 204);
        $this->assertMatchesContract($this->deleteJson('/api/admin/books'), 'admin.books.delete', 404);
        $this->assertMatchesContract($this->getJson('/api/admin/books/records'), 'admin.books.records.list', 404);
        $this->assertCount(0, $this->ninja->of($connection)['webhooks']);
    }

    public function test_connecting_is_limited(): void
    {
        $this->actingAsAdmin();

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/admin/books', ['token' => 'in-platform-token']);
        }

        $this->assertMatchesContract($this->postJson('/api/admin/books', ['token' => 'in-platform-token']), 'admin.books.connect', 429);
    }
}
