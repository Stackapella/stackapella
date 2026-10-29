<?php

declare(strict_types=1);

namespace Tests\Feature\Books;

use App\Actions\Users\DeleteAccount;
use App\Models\CoachingPayment;
use App\Models\Engagement;
use App\Models\InvoiceNinjaRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * A deleted buyer leaves our money records: the amounts and dates stay, without who it was.
 */
final class DeletedBuyerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_deleted_singers_name_and_address_leave_the_payments_and_book_rows(): void
    {
        Queue::fake();
        $ana = User::factory()->create(['name' => 'Ana Ruiz', 'email' => 'ana@example.com'])->fresh() ?? throw new \LogicException;
        $coach = User::factory()->create(['name' => 'Coach Kim']);
        $engagement = Engagement::factory()->create(['singer_id' => $ana->id, 'coach_id' => $coach->id, 'status' => Engagement::DELIVERED]);
        $payment = CoachingPayment::factory()->create(['engagement_id' => $engagement->id, 'coach_id' => $coach->id, 'description' => 'Take review: My break, for Ana Ruiz']);
        $sale = InvoiceNinjaRecord::factory()->create(['ref' => $payment->ref, 'data' => ['description' => $payment->description, 'name' => 'Ana Ruiz', 'email' => 'ana@example.com']]);
        $plus = InvoiceNinjaRecord::factory()->create(['kind' => InvoiceNinjaRecord::PLUS, 'ref' => 'plus:in_1', 'user_id' => $ana->id, 'data' => ['description' => 'Stackapella Plus', 'name' => 'Ana Ruiz', 'email' => 'ana@example.com']]);
        $commission = InvoiceNinjaRecord::factory()->create(['kind' => InvoiceNinjaRecord::COMMISSION, 'ref' => 'commission:1', 'user_id' => $coach->id, 'data' => ['name' => 'Coach Kim', 'email' => 'kim@example.com', 'lines' => [['description' => 'Take review: My break, for Ana Ruiz (25.00 GBP)', 'cents' => 375]]]]);

        app(DeleteAccount::class)->handle($ana, '203.0.113.9');

        $this->assertSame('Take review: My break, for A deleted account', $payment->refresh()->description);
        $this->assertSame(2500, $payment->cents);
        foreach ([$sale, $plus] as $record) {
            $data = (array) $record->refresh()->data;
            $this->assertSame('A deleted account', $data['name']);
            $this->assertStringEndsWith('@users.stackapella.invalid', (string) $data['email']);
            $this->assertStringNotContainsString('Ana Ruiz', (string) json_encode($data));
        }

        $data = (array) $commission->refresh()->data;
        $this->assertSame('Coach Kim', $data['name']);
        $this->assertSame('Take review: My break, for A deleted account (25.00 GBP)', $data['lines'][0]['description']);
    }
}
