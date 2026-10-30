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
    'coaching' => ['kind' => 'feature', 'values' => ['off', 'on']],
    // Coaches apply and are approved (docs/launch-plan.md: open at launch), while the directory
    // (coach_directory, N4) keeps them out of every public list until it opens.
    'coach_signup' => ['kind' => 'feature', 'values' => ['off', 'on']],
    // Hum a tune's page and the landing's line about it (docs/launch-plan.md: on at launch).
    'your_tune' => ['kind' => 'feature', 'values' => ['off', 'on']],
    // The comparison pages (docs/compare-pages-plan.md): /compare and /compare/<slug> answer either
    // way, but stay out of search and the sitemap, with no footer link, until it's on.
    'compare_pages' => ['kind' => 'feature', 'values' => ['off', 'on']],
    'event_schedule' => ['kind' => 'feature', 'values' => ['off', 'on']],
    // The open-source launch (docs/open-source-plan.md, 5.1): /open-source, /selfhost, the
    // self-hosting terms and every mention of it on the site. Off until the repos are public:
    // saying so before then would be untrue.
    'open_source' => ['kind' => 'feature', 'values' => ['off', 'on']],

];
