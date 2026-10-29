<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\CheckoutSession;
use App\Models\User;
use App\Services\Billing\BillingGateway;
use Illuminate\Testing\TestResponse;

/**
 * Plus in the tests: the fake gateway, and Stripe's webhook as Stripe would send it.
 */
trait Plus
{
    protected FakeBillingGateway $stripe;

    protected function fakeStripe(): FakeBillingGateway
    {
        $this->stripe = new FakeBillingGateway;
        $this->app->instance(BillingGateway::class, $this->stripe);
        // Stripe "set up" (a test key and prices), so Checkout is on; the gateway never calls it.
        config(['cashier.secret' => 'sk_test_fake', 'billing.prices' => ['month' => 'price_month', 'year' => 'price_year']]);

        return $this->stripe;
    }

    protected function member(bool $verified = true): User
    {
        $factory = $verified ? User::factory() : User::factory()->withPassword()->unverified();

        return $factory->create()->fresh() ?? throw new \LogicException('No user.');
    }

    /**
     * The account as the next request loads it (its subscriptions read again).
     */
    protected function fresh(User $user): User
    {
        return User::query()->findOrFail($user->id);
    }

    /**
     * @param  array<string, mixed>  $object
     */
    protected function stripeEvent(string $type, array $object): TestResponse
    {
        return $this->postJson('/webhooks/stripe', ['id' => 'evt_'.uniqid(), 'type' => $type, 'data' => ['object' => $object]]);
    }

    /**
     * @param  array<string, mixed>  $with
     * @return array<string, mixed>
     */
    protected function subscription(User $user, string $status = 'active', array $with = []): array
    {
        return [
            'id' => 'sub_'.$user->id,
            'customer' => $user->stripe_id ?? 'cus_test_'.$user->id,
            'status' => $status,
            'trial_end' => $status === 'trialing' ? now()->addDays(7)->getTimestamp() : null,
            'current_period_end' => now()->addYear()->getTimestamp(),
            'metadata' => ['type' => 'default'],
            'items' => ['data' => [['id' => 'si_'.$user->id, 'quantity' => 1, 'price' => ['id' => 'price_year', 'product' => 'prod_plus']]]],
            ...$with,
        ];
    }

    protected function subscribe(User $user, string $status = 'active'): void
    {
        $user->forceFill(['stripe_id' => $user->stripe_id ?? 'cus_test_'.$user->id])->save();
        $session = CheckoutSession::query()->where('user_id', $user->id)->latest('id')->value('stripe_id');

        if (is_string($session)) {
            $this->stripeEvent('checkout.session.completed', ['id' => $session, 'customer' => $user->stripe_id])->assertOk();
        }

        $this->stripeEvent('customer.subscription.created', $this->subscription($user, $status))->assertOk();
    }
}
