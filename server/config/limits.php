<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Rate limits (docs/trust-and-safety-plan.md, "The limits")
|--------------------------------------------------------------------------
|
| Every named limiter, in one place. Each has one or more rules, and a request must pass them all.
| Hillel can change any rule's `max` live in admin, without a deploy (stored as an override in the
| settings table; App\Support\Trust\Limits). A refusal is a 429 with Retry-After and the code
| rate_limited.
|
| `by` says whose count it is:
| - account:       the signed-in user (a signed-out request counts against its IP instead);
| - ip:            the client's real IP (App\Support\Trust\ClientIp), kept only as an HMAC;
| - email:         the email address in the request (lower-cased, HMAC'd);
| - ip_and_email:  the two together.
| `when` (optional) limits a rule to signed_in or signed_out requests, or to one trust level
| (level:1, level:2, level:3).
|
| Limits for later milestones join this file with the feature that needs them.
|
*/

return [

    'api' => [
        'signed_in' => ['max' => 300, 'per' => 'minute', 'by' => 'account', 'when' => 'signed_in'],
        'signed_out' => ['max' => 60, 'per' => 'minute', 'by' => 'ip', 'when' => 'signed_out'],
    ],

    // The health check (App Platform and uptime monitors call it every few seconds).
    'health' => [
        'ip' => ['max' => 120, 'per' => 'minute', 'by' => 'ip'],
    ],

    // The public pages (/h/<id>): far above anyone reading, a wall for scrapers.
    'public-pages' => [
        'ip' => ['max' => 120, 'per' => 'minute', 'by' => 'ip'],
    ],

    // Sign in with Google or Apple: the trips out to the provider and back.
    'provider-sign-in' => [
        'ip' => ['max' => 20, 'per' => 'minute', 'by' => 'ip'],
    ],

    'second-factor' => [
        'account' => ['max' => 10, 'per' => 'minute', 'by' => 'account'],
    ],

    // The beta's invite links and codes: never guessable by trying (docs/beta-singers-plan.md).
    'beta-link' => [
        'ip' => ['max' => 20, 'per' => 'hour', 'by' => 'ip'],
    ],

    // Project and choir links: never guessable by trying, like the beta's.
    'invite-links' => [
        'ip' => ['max' => 30, 'per' => 'hour', 'by' => 'ip'],
    ],

    'sign-up' => [
        'ip_hour' => ['max' => 5, 'per' => 'hour', 'by' => 'ip'],
        'ip_day' => ['max' => 20, 'per' => 'day', 'by' => 'ip'],
    ],

    'verification-email' => [
        'email_minute' => ['max' => 1, 'per' => 'minute', 'by' => 'email'],
        'email_hour' => ['max' => 3, 'per' => 'hour', 'by' => 'email'],
        'email_day' => ['max' => 6, 'per' => 'day', 'by' => 'email'],
        // Counted only on a resend (sign-up has its own per-IP limit), and never answered as a
        // refusal: past it, nothing more is sent from that network.
        'resend_ip_hour' => ['max' => 10, 'per' => 'hour', 'by' => 'ip'],
        'resend_ip_day' => ['max' => 30, 'per' => 'day', 'by' => 'ip'],
    ],

    'sign-in' => [
        'ip_and_email' => ['max' => 10, 'per' => 'minute', 'by' => 'ip_and_email'],
        'ip' => ['max' => 30, 'per' => 'minute', 'by' => 'ip'],
    ],

    'password-reset' => [
        'email' => ['max' => 3, 'per' => 'hour', 'by' => 'email'],
        'ip' => ['max' => 10, 'per' => 'hour', 'by' => 'ip'],
    ],

    // Following the reset link (the token itself can't be guessed): typing a weak password twice
    // mustn't lock anyone out.
    'password-reset-link' => [
        'ip' => ['max' => 10, 'per' => 'minute', 'by' => 'ip'],
    ],

    'email-change' => [
        'account' => ['max' => 3, 'per' => 'day', 'by' => 'account'],
    ],

    // Bytes of takes one account uploads in a day (docs/trust-and-safety-plan.md, "Uploaded
    // bytes"): stops a run of record-and-delete before the storage allowance would. Counted by
    // hand in StartTake, by the uploader's plan, in megabytes (what admin can type).
    'upload-bytes' => [
        'free_mb' => ['max' => 2000, 'per' => 'day', 'by' => 'account'],
        'plus_mb' => ['max' => 10000, 'per' => 'day', 'by' => 'account'],
    ],

    'take-uploads' => [
        'hour' => ['max' => 60, 'per' => 'hour', 'by' => 'account'],
        'day' => ['max' => 400, 'per' => 'day', 'by' => 'account'],
    ],

    // Each time a harmony becomes unlisted (counted by the action, not the route: making a private
    // one, or asking for the same video again, counts toward nothing).
    'shares' => [
        'level_1' => ['max' => 3, 'per' => 'day', 'by' => 'account', 'when' => 'level:1'],
        'level_2' => ['max' => 20, 'per' => 'day', 'by' => 'account', 'when' => 'level:2'],
        'level_3' => ['max' => 50, 'per' => 'day', 'by' => 'account', 'when' => 'level:3'],
    ],

    // Messages in a coaching engagement (messages.create): plenty for a conversation, never a flood.
    'coaching-messages' => [
        'hour' => ['max' => 60, 'per' => 'hour', 'by' => 'account'],
        'day' => ['max' => 300, 'per' => 'day', 'by' => 'account'],
    ],

    'share-videos' => [
        'hour' => ['max' => 3, 'per' => 'hour', 'by' => 'account'],
        'day' => ['max' => 10, 'per' => 'day', 'by' => 'account'],
    ],

    // The apps' purchases and restores (billing.store.sync): each checks with Apple or Google.
    'store-sync' => [
        'account' => ['max' => 30, 'per' => 'hour', 'by' => 'account'],
    ],

    'checkout' => [
        'account' => ['max' => 5, 'per' => 'hour', 'by' => 'account'],
    ],

    // Event Schedule (M9): starting a connection, and a lesson's free times (each asks ES at most
    // every 30 seconds, but someone paging through days still makes calls).
    'event-schedule-connect' => [
        'account' => ['max' => 10, 'per' => 'hour', 'by' => 'account'],
    ],

    // Contact us and Send diagnostics (signed in or not).
    'support' => [
        'account' => ['max' => 5, 'per' => 'hour', 'by' => 'account'],
    ],

    // A new photo is decoded, stored and purged from the CDN each time.
    'avatar' => [
        'account' => ['max' => 20, 'per' => 'hour', 'by' => 'account'],
    ],

    // A device's push subscription (the server posts to each one).
    'push-devices' => [
        'account' => ['max' => 20, 'per' => 'hour', 'by' => 'account'],
    ],

    // Connecting Invoice Ninja checks the token with it each time.
    'books-connect' => [
        'account' => ['max' => 10, 'per' => 'hour', 'by' => 'account'],
    ],

    'lesson-slots' => [
        'ip' => ['max' => 60, 'per' => 'minute', 'by' => 'ip'],
    ],

    // The browser's error reports (the Sentry tunnel): a broken page mustn't flood it.
    // Part leaderboards: a score after each take is plenty; challenge links are handed out by hand.
    'board-scores' => [
        'account' => ['max' => 30, 'per' => 'minute', 'by' => 'account'],
    ],
    'board-challenges' => [
        'account' => ['max' => 20, 'per' => 'hour', 'by' => 'account'],
    ],

    // Anonymous usage counts: a beacon now and then from each app; more is someone inflating them.
    'counts' => [
        'ip' => ['max' => 30, 'per' => 'minute', 'by' => 'ip'],
    ],

    'error-reports' => [
        'ip' => ['max' => 60, 'per' => 'minute', 'by' => 'ip'],
    ],

    // Event Schedule's webhooks: generous (each is signed), but bounded per sender.
    'webhooks' => [
        'ip' => ['max' => 600, 'per' => 'minute', 'by' => 'ip'],
    ],

    'data-export' => [
        'account' => ['max' => 3, 'per' => 'hour', 'by' => 'account'],
    ],

    'reports' => [
        'account' => ['max' => 10, 'per' => 'hour', 'by' => 'account'],
    ],

    // The gallery (N1; the trust and safety plan's "When the gallery opens"). Doing it twice is a
    // no-op, so only new likes, follows and comments count.
    'comments' => [
        'level_2' => ['max' => 10, 'per' => 'hour', 'by' => 'account', 'when' => 'level:2'],
        'level_3' => ['max' => 30, 'per' => 'hour', 'by' => 'account', 'when' => 'level:3'],
    ],

    // Publishing to the template library (N3): five a day.
    'template-publish' => [
        'account' => ['max' => 5, 'per' => 'day', 'by' => 'account'],
    ],

    // Open parts' stems (N5): sixty an hour.
    'stems' => [
        'account' => ['max' => 60, 'per' => 'hour', 'by' => 'account'],
    ],

    // The apps' refresh (auth.app.refresh): sixty an hour for one account, counted once the token
    // is read, and a backstop for one network that a carrier's shared IP won't reach.
    'app-refresh' => [
        'ip' => ['max' => 1200, 'per' => 'hour', 'by' => 'ip'],
        'account' => ['max' => 60, 'per' => 'hour', 'by' => 'account', 'when' => 'signed_in'],
    ],

    // Sharing ideas: ten a day.
    'ideas-create' => [
        'account' => ['max' => 10, 'per' => 'day', 'by' => 'account'],
    ],

    // Voices on ideas: twenty an hour.
    'idea-voices' => [
        'account' => ['max' => 20, 'per' => 'hour', 'by' => 'account'],
    ],

    'follows' => [
        'account' => ['max' => 50, 'per' => 'hour', 'by' => 'account'],
    ],

    'likes' => [
        'account' => ['max' => 200, 'per' => 'hour', 'by' => 'account'],
    ],

    // Plus songs' full templates (templates.show): a daily cap against scraping the library; a
    // song fetched again the same day, or a 304, isn't counted (docs/trust-and-safety-plan.md).
    'plus-downloads' => [
        'account' => ['max' => 60, 'per' => 'day', 'by' => 'account'],
    ],

    // Coaching (contract/api.md): applications, and each thread's messages.
    'coach-applications' => [
        'account' => ['max' => 10, 'per' => 'day', 'by' => 'account'],
    ],

    'coach-messages' => [
        'account' => ['max' => 30, 'per' => 'hour', 'by' => 'account'],
    ],

    'contact' => [
        'ip' => ['max' => 5, 'per' => 'hour', 'by' => 'ip'],
    ],

];
