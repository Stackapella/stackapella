<?php

declare(strict_types=1);

// Hillel's saved replies in the support inbox (docs/operations-plan.md, "Saved replies"), drafted
// for him to edit before sending. Plain text; a blank line starts a new paragraph.
return [
    'saved' => [
        'silent_mic' => [
            'title' => 'The mic is silent',
            'body' => "Thanks for writing. When the app can't hear you, it's usually one of three things: the browser hasn't been allowed to use the mic, another app is using it, or the wrong mic is picked.\n\nIn the app, open the mic picker on the recording screen, choose your mic, and sing a note: the level meter should move. On an iPhone, check Settings, then Safari, then Microphone.\n\nIf it still stays silent, reply with the device and browser you're using, and I'll look into it with you.",
        ],
        'out_of_time' => [
            'title' => 'Takes are out of time',
            'body' => "Thanks for writing. Every device adds its own delay between what you hear and what's recorded, and the timing check measures it.\n\nPlease run the timing check again (from the menu on the recording screen), with headphones if you can, and record a short take. If it still sounds early or late, Nudge on the part moves it by a few milliseconds.\n\nIf that doesn't fix it, reply with the device and browser you're using, and I'll look at it with you.",
        ],
        'iphone' => [
            'title' => 'Installing on iPhone',
            'body' => "Thanks for writing. On an iPhone, open app.stackapella.com, tap the Share button, then Add to Home Screen. The app then opens full screen from its icon, and keeps your songs on the phone.\n\nIf you don't see Add to Home Screen, open the page in Safari and try again.",
        ],
        'refunds' => [
            'title' => 'Refunds',
            'body' => "Thanks for writing. I've refunded your last payment for Plus; it reaches your card in 5 to 10 days, depending on your bank.\n\nThe refund policy is on our legal pages if you'd like the details.",
        ],
        'refunds_store' => [
            'title' => 'Refunds for Plus bought in an app',
            'body' => "Thanks for writing. Plus bought in the App Store is refunded by Apple rather than by us: ask at reportaproblem.apple.com, sign in with your Apple ID, and choose the Stackapella subscription. Apple decides, usually within a couple of days.\n\nIf you bought it in Google Play, I've refunded it there; it reaches you in a few days, depending on how you paid.",
        ],
        'cancelling' => [
            'title' => 'Cancelling Plus',
            'body' => "Thanks for writing. You can cancel any time in the app: Settings, then Plan and storage, then Manage your plan. Plus stays on until the end of the period you've paid for, and nothing more is charged.\n\nIf you bought Plus in the App Store or Google Play, cancel it there instead: on an iPhone, Settings, then your name, then Subscriptions; on Android, Google Play, then Payments and subscriptions.",
        ],
        'lost_account' => [
            'title' => 'A lost account',
            'body' => "Thanks for writing. To make sure the account is yours, please reply with the email address on it, the titles of a couple of your songs, and if you pay for Plus, the date of a recent receipt.\n\nOnce I've checked, I'll add a way for you to sign in again and let you know.",
        ],
    ],
];
