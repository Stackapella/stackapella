<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| The public site's features: what's open, and what's coming soon
|--------------------------------------------------------------------------
|
| Every feature the public pages talk about (docs/public-site-plan.md). One with a `flag` is open
| when that flag is on (config/flags.php, App\Support\Flags); one without is open when `live` is
| true, changed here when it ships. Anything not open shows one "Coming soon" label until then
| (App\Support\Site\ComingSoon); its page is still indexed, so it can rank by launch (Hillel).
|
*/

return [

    // Where people write to us (docs/decisions.md: one address people can reply to). Each of these
    // is a setting, so a self-hosted install names its own (docs/open-source-plan.md, 5.5).
    'contact_email' => env('SITE_CONTACT_EMAIL') ?: 'contact@stackapella.com',

    // Legal questions, copyright notices and the coach agreement (Hillel, 2026-10-29): the legal
    // pages say legal@stackapella.com, shown as this.
    'legal_email' => env('SITE_LEGAL_EMAIL') ?: 'legal@stackapella.com',

    // Who makes it, as About and the press kit name him, linked to his site (Hillel, 2026-10-27).
    'founder' => env('SITE_FOUNDER') ?: 'Hillel Coren',
    'founder_url' => env('SITE_FOUNDER_URL') ?: 'https://hillel.dev',

    // The footer's social links (Hillel, 2026-10-27); an empty one isn't shown. X's handle is still
    // to be confirmed. A self-hosted install sets its own, or "none" for no link.
    'social' => [
        'x' => (string) env('SITE_X_URL', ''),
        'bluesky' => env('SITE_BLUESKY_URL') === 'none' ? '' : (env('SITE_BLUESKY_URL') ?: 'https://bsky.app/profile/stackapella.com'),
    ],

    // The landing's film (server/resources/promo/showreel/README.md): its files at this base URL, the
    // CDN in production (the media CDN's /showreel, where app:showreel:upload puts each render in a
    // folder named by its manifest's hash), server/public/videos/showreel locally (the hash as a
    // query). Either way a re-render reaches everyone though the names stay. Worked out from the
    // media disk (Hillel, 2026-10-29: "I shouldn't need this ENV"): Spaces with a CDN_URL means
    // CDN_URL/showreel; SHOWREEL_URL still overrides it.
    'showreel' => [
        'url' => rtrim((string) (env('SHOWREEL_URL') ?: (env('MEDIA_DISK') === 'spaces_media' && is_string(env('CDN_URL')) && env('CDN_URL') !== '' ? rtrim((string) env('CDN_URL'), '/').'/showreel' : '/videos/showreel')), '/'),
    ],

    // The code, once it's open (docs/open-source-plan.md; shown only with the open_source flag on).
    // The repos' home is decision 2 in that plan, recommended as a stackapella organisation.
    'source' => [
        'repo' => 'https://github.com/stackapella/stackapella',
        'licence' => 'https://opensource.org/license/attribution-php',
    ],

    'features' => [
        'learn-a-part' => ['live' => true],
        'course' => ['live' => true],
        'studio' => ['live' => true],
        'real-voices' => ['live' => true],
        'share' => ['live' => true],
        'create' => ['live' => true],
        'choirs' => ['live' => true],
        // Hum a tune (docs/your-tune-plan.md). Start from chords is part of create.
        'your-tune' => ['flag' => 'your_tune'],
        'ideas' => ['flag' => 'ideas'],
        'coaching' => ['flag' => 'coaching'],
        'coach-directory' => ['flag' => 'coach_directory'],
        'gallery' => ['flag' => 'gallery'],
        'open-parts' => ['flag' => 'open_parts'],
        'leagues' => ['flag' => 'leagues'],
        'library' => ['flag' => 'template_library'],
        'virtual-choir' => ['flag' => 'virtual_choir'],
        'song-requests' => ['flag' => 'song_requests'],
        // The phone and desktop apps (docs/roadmap.md, "The native apps"): store badges once live.
        'apps' => ['live' => false],
    ],

];
