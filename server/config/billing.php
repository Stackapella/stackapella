<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Plus billing
|--------------------------------------------------------------------------
|
| docs/pricing-plan.md ("Prices", "At checkout") and docs/backend-plan.md D8: Stripe Checkout and
| the portal through Cashier, with Stripe Tax. Two Stripe prices (monthly and yearly), each with
| currency options for EUR and GBP; the amounts here are what the app shows before Checkout, and
| must match Stripe's. Prices include tax where the law expects it (EUR, GBP); USD adds it.
| Keys stay in env (STRIPE_KEY, STRIPE_SECRET, STRIPE_WEBHOOK_SECRET, read by Cashier's config).
|
*/

return [

    'prices' => [
        'month' => env('STRIPE_PRICE_MONTH'),
        'year' => env('STRIPE_PRICE_YEAR'),
    ],

    'amounts' => [
        'usd' => ['month' => 499, 'year' => 3999, 'tax_inclusive' => false],
        'eur' => ['month' => 499, 'year' => 3999, 'tax_inclusive' => true],
        'gbp' => ['month' => 449, 'year' => 3499, 'tax_inclusive' => true],
    ],

    // No free trial since 2026-11-21 (Hillel: "better to charge people from the start"): 0 turns
    // it off everywhere (Checkout starts paid, nothing offers one). Anyone already in one keeps it to
    // its end. Set above 0, the old trial comes back: yearly with a card for anyone who hasn't had
    // one; monthly without a card only from trust level 2 (Singer).
    'trial_days' => 0,
    'no_card_trial_level' => 2,

    // Plus through Stripe stops this many days after a failed payment's last retry, whatever Stripe
    // does with the subscription (the billing review, 2026-11-21).
    'past_due_grace_days' => 3,

    // How long a Checkout counts as "pending" without the webhook.
    'pending_minutes' => 30,

    // The 14-day offline grace for Plus songs (docs/operations-plan.md).
    'offline_days' => 14,

    // A card that had a trial: the notice lasts this long.
    'notice_days' => 7,

    // The EU, EEA and UK consent to start Plus at once (App\Support\Billing\Withdrawal): the
    // version of the words the app shows, moved on when the lawyer changes them.
    'start_now_consent_version' => 1,

    // Stripe's own "I agree to the terms" box on Checkout: on once the terms' address is set in
    // Stripe's settings (Stripe refuses it before).
    'terms_checkbox' => (bool) env('STRIPE_TERMS_CHECKBOX', false),

];
