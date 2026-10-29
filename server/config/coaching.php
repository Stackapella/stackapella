<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Coaching, without the directory (docs/coaching-marketplace-plan.md)
|--------------------------------------------------------------------------
|
| Coaches sell take reviews, monthly coaching and exercise packs through their own profile link.
| Singers pay Stackapella (Stripe), which keeps the commission and moves the coach's share to their
| Stripe Express account.
|
*/

return [

    // Stackapella's share of each sale (proposed 15%; open, docs/decisions.md).
    'commission_percent' => is_numeric(env('COACHING_COMMISSION_PERCENT')) ? (int) env('COACHING_COMMISSION_PERCENT') : 15,

    'currencies' => ['usd', 'eur', 'gbp'],
    'min_price_cents' => 500,
    'max_price_cents' => 100000,

    // A requested review: the coach accepts within this, then delivers within their turnaround.
    'accept_hours' => 48,
    'turnaround_days' => [2, 7],
    'unsend_minutes' => 10,
    // The coach's access to what was sent ends this long after an engagement ends.
    'access_days' => 90,
    'rating_edit_days' => 30,
    'ratings_to_show' => 3,

    // Live lessons, booked in Event Schedule (M9, G2): a coach's default cancellation policy (full
    // refund at least this many hours ahead); how long a lesson needing approval may wait (never
    // past `approval_before_hours` before its start); the takes a singer may send before one; and
    // when a lesson nobody cancelled counts as done (hours after its end).
    'cancellation_hours' => 24,
    'approval_before_hours' => 2,
    'lesson_takes' => 1,
    'lesson_complete_hours' => 24,
    'reapply_days' => 30,

    // The coach agreement's version (resources/legal), agreed at the application.
    'agreement_version' => 1,

    // The take a would-be coach reviews in their application (on the media bucket).
    'sample_take_key' => env('COACHING_SAMPLE_TAKE_KEY') ?: 'coaching/sample-take.m4a',

    // What the singer sends: the take's pitch track as the device made it.
    'pitch_track_bytes' => 1024 * 1024,

];
