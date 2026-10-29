<?php

declare(strict_types=1);

return [
    // The pages a browser lands on when something goes wrong (views/errors): words only, never
    // what happened inside, always a way on.
    'not_found' => [
        'title' => 'This page isn\'t here',
        'body' => 'The link may be old, or the address mistyped. Everything else is where it was.',
    ],
    'forbidden' => [
        'title' => 'This page isn\'t open to you',
        'body' => 'The link may have expired, or be meant for someone else. If someone sent it to you, ask them for a fresh one.',
    ],
    'expired' => [
        'title' => 'That form timed out',
        'body' => 'For your safety, a form left open for a long time stops working. Go back, reload the page and try again.',
    ],
    'server' => [
        'title' => 'Something went wrong on our side',
        'body' => 'It isn\'t anything you did, and your songs are safe. Try again in a moment.',
    ],
    'maintenance' => [
        'title' => 'Back in a few minutes',
        'body' => 'We\'re making Stackapella better. Your songs are safe on your device, and the app keeps working offline.',
    ],
    'home' => 'Back to the start',
    'onward' => 'Keep looking',
    'app' => 'Open the app',
    'rate_limited' => [
        'title' => 'Slow down a little',
        'body' => '{1} You can try again in a minute.|[2,*] You can try again in :minutes minutes.',
    ],
];
