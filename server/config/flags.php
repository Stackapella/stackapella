<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Flags: the switches for an attack, and features that ship dark
|--------------------------------------------------------------------------
|
| Stored in the `flags` table (App\Support\Flags), so changing one never needs a deploy. Every
| change is audit-logged with a reason; in admin it needs sudo and the admin role.
|
| - switch:  a protection Hillel turns on during an attack (docs/trust-and-safety-plan.md,
|            "Switches for an attack"). `values` lists the allowed values; the first is "off".
| - feature: something built but not yet open. Off for everyone until switched on, and can be
|            turned on for single people first (flag_users).
|
| `pauses` names what the switch stops: routes use the `paused:<name>` middleware, which answers
| 503 with the code `paused`.
|
*/

return [

    'shield_mode' => ['kind' => 'switch', 'values' => ['off', 'on']],
    'pause_signups' => ['kind' => 'switch', 'values' => ['off', 'password', 'all']],
    'pause_sharing' => ['kind' => 'switch', 'values' => ['off', 'on'], 'pauses' => 'sharing'],
    'pause_videos' => ['kind' => 'switch', 'values' => ['off', 'on'], 'pauses' => 'videos'],
    'pause_uploads' => ['kind' => 'switch', 'values' => ['off', 'on'], 'pauses' => 'uploads'],
    // A burst of failed payments is card testing: Checkout pauses (docs/trust-and-safety-plan.md).
    'pause_checkout' => ['kind' => 'switch', 'values' => ['off', 'on'], 'pauses' => 'checkout'],
    'pause_comments' => ['kind' => 'switch', 'values' => ['off', 'on'], 'pauses' => 'comments'],
    'pause_follows' => ['kind' => 'switch', 'values' => ['off', 'on'], 'pauses' => 'follows'],
    'pause_requests' => ['kind' => 'switch', 'values' => ['off', 'on'], 'pauses' => 'requests'],
    'pause_messages' => ['kind' => 'switch', 'values' => ['off', 'on'], 'pauses' => 'messages'],
    'read_only' => ['kind' => 'switch', 'values' => ['off', 'on']],
    // Hillel's switch for outreach (2026-11-05: "can you auto send 5 a day"; docs/outreach-plan.md):
    // on, one drafted first email goes every 30 minutes on weekday mornings until the daily cap
    // (App\Actions\Outreach\AutoSendOutreach). Follow-ups stay his own Send.
    'outreach_autosend' => ['kind' => 'switch', 'values' => ['off', 'on']],
    // Hillel's switch for IndexNow (2026-11-09: "Yes, build it"): on, every hour our public pages
    // that are new to the sitemap or whose words changed go to api.indexnow.org, at most
    // services.indexnow.daily_cap a day (App\Actions\Site\SubmitToIndexNow). Never self-hosted.
    'indexnow' => ['kind' => 'switch', 'values' => ['off', 'on']],

    'gallery' => ['kind' => 'feature', 'values' => ['off', 'on']],
    // The other network features (N2 to N7; docs/roadmap.md, "After launch"), each opened here.
    'leagues' => ['kind' => 'feature', 'values' => ['off', 'on']],
    'template_library' => ['kind' => 'feature', 'values' => ['off', 'on']],
    'coach_directory' => ['kind' => 'feature', 'values' => ['off', 'on']],
    'open_parts' => ['kind' => 'feature', 'values' => ['off', 'on']],
    'virtual_choir' => ['kind' => 'feature', 'values' => ['off', 'on']],
    'song_requests' => ['kind' => 'feature', 'values' => ['off', 'on']],
    // Ideas (docs/your-tune-plan.md, step 3): the Ideas shelf, which may open before the gallery.
    'ideas' => ['kind' => 'feature', 'values' => ['off', 'on']],
    // The Plus courses (docs/plus-courses-plan.md): staff see them while it's off, everyone once on.
    'plus_courses' => ['kind' => 'feature', 'values' => ['off', 'on']],
    // A lesson page's "Try one round" (docs/lesson-page-try-it-plan.md): a listen-and-choose round on the page, no mic.
    'lesson_try' => ['kind' => 'feature', 'values' => ['off', 'on']],
    'coaching' => ['kind' => 'feature', 'values' => ['off', 'on']],
    // Coaches apply and are approved (docs/launch-plan.md: open at launch), while the directory
    // (coach_directory, N4) keeps them out of every public list until it opens.
    'coach_signup' => ['kind' => 'feature', 'values' => ['off', 'on']],
    // Hum a tune's page and the landing's line about it (docs/launch-plan.md: on at launch).
    'your_tune' => ['kind' => 'feature', 'values' => ['off', 'on']],
    // Sheet music import (docs/sheet-music-plan.md): off until the server's guardrails are live; `directors` opens
    // it to choir directors only (docs/score-import-plan.md: learning tracks from their own score). Either needs
    // the DMCA agent named (App\Support\Templates\SheetImport).
    'sheet_import' => ['kind' => 'feature', 'values' => ['off', 'on', 'directors']],
    // Lend your voice (docs/lend-your-voice-plan.md): volunteers claim the songs' and lessons'
    // missing real voices. Off until Hillel has reviewed the agreement and the terms.
    'lend_voice' => ['kind' => 'feature', 'values' => ['off', 'on']],
    // The school offer (docs/school-offer-plan.md): teachers' class codes for a free year of Plus.
    // Off until Hillel has read its terms and privacy lines.
    'schools' => ['kind' => 'feature', 'values' => ['off', 'on']],
    // The comparison pages (docs/compare-pages-plan.md): /compare and /compare/<slug> answer either
    // way, but stay out of search and the sitemap, with no footer link, until it's on.
    'compare_pages' => ['kind' => 'feature', 'values' => ['off', 'on']],
    'event_schedule' => ['kind' => 'feature', 'values' => ['off', 'on']],
    // The open-source launch (docs/open-source-plan.md, 5.1): /open-source, /selfhost, the
    // self-hosting terms and every mention of it on the site. Off until the repos are public:
    // saying so before then would be untrue.
    'open_source' => ['kind' => 'feature', 'values' => ['off', 'on']],
    // A language for everyone (docs/languages-plan.md; App\Support\Languages\Languages): the app
    // offers it in Settings and follows the browser into it, and the site serves its pages, only
    // while it's on. Off until its slice has had its native-speaker review.
    'lang_es' => ['kind' => 'feature', 'values' => ['off', 'on']],
    'lang_de' => ['kind' => 'feature', 'values' => ['off', 'on']],
    // Songs by country (docs/geo-rights-plan.md, App\Support\Rights\RightsHere): a song, or its words, whose
    // copyright still runs in the visitor's country is withheld there. Off until every open song has its makers and
    // the lawyer has read the rules; Hillel switches it on.
    'geo_rights' => ['kind' => 'feature', 'values' => ['off', 'on']],

];
