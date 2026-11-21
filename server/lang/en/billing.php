<?php

declare(strict_types=1);

return [
    'return' => [
        'done_title' => 'You\'re on Plus',
        'done_body' => 'Thank you. Plus is on: room for every take, stems and MP3, private arrangements and singing together.',
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
    // On every Stripe receipt and invoice once agreed (a durable copy of the consent; Stripe allows 40 and 140 characters).
    'start_now_receipt_name' => 'Plus started at once',
    'start_now_receipt' => 'On :date you asked for Plus to start at once and agreed that you lose the 14-day right to cancel for the time used.',

    // A choir's seats on Stripe's Checkout page (docs/choir-seats-plan.md), above the button.
    'seats_terms' => [
        'renews' => ':seats seats for your choir, at :seat a seat: :price a year, renewing until you cancel. Change the seats or cancel online any time in Settings.',
        'trial' => 'Your choir sings free until :date. Then :seats seats, at :seat a seat: :price a year, renewing until you cancel. Change the seats or cancel online any time in Settings, before :date to pay nothing.',
    ],
];
