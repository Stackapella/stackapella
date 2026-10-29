<?php

declare(strict_types=1);

return [
    'return' => [
        'done_title' => 'You\'re on Plus',
        'done_body' => 'Thank you. Every song in the library is yours to sing now.',
        'waiting_title' => 'Still confirming...',
        'waiting_body' => 'Stripe is telling us about your payment; it takes a few seconds. You can go back to the app now: Plus turns on by itself.',
        'back' => 'Go back to the app',
    ],

    // Shown on Stripe's Checkout page, above the button (docs/pricing-plan.md, "At checkout").
    'checkout_terms' => [
        'renews' => 'Plus renews every :interval at :price until you cancel. Cancel online any time in Settings.',
        'trial' => 'Your free trial ends on :date. Then Plus renews every :interval at :price until you cancel. Cancel online any time in Settings, before :date to pay nothing.',
        'trial_no_card' => 'Your free trial ends on :date, and Plus ends with it unless you add a card in Settings. With a card, Plus renews every :interval at :price until you cancel. Cancel online any time in Settings.',
        'plus_tax' => ':price plus tax',
        'interval' => ['month' => 'month', 'year' => 'year'],
    ],

    'start_now_consent_required' => 'Please confirm that Plus should start now.',
];
