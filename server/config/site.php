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
    // The carols page's "a new carol opens every Monday" line shows until this date, the last Monday before Christmas
    // in this year's plan (docs/library-next-songs.md), then goes by itself (App\Support\Pages\Carols::weekly).
    // None since 2026-11-10: Hillel opened all six landed carols that week, so no carol opens weekly after it.
    'carols_weekly_until' => null,
    // When the carols are pushed each year (Hillel, 2026-11-10: "yes do the christmas page improvements"): the
    // landing's one line and the seasonal links on choir pages show from this month-day to this one, then hide by
    // themselves (App\Support\Pages\Carols::promoted). The carols page itself stays.
    'carols_promoted' => ['from' => '11-01', 'until' => '12-26'],
    // The festivals calendar (Hillel, 2026-11-12: "have the main songs page automatically highlight which section is
    // coming up next"): the one source for every date-aware collection, App\Support\Pages\Festivals. Each: its key (its
    // name is site.festivals.<key>), the collection page it leads to, its first day, whether it begins at sundown the
    // evening of that day (then it runs `days` days after it), how many days it runs, and how many days before it /songs
    // starts leading with it as "Coming up" (`ahead`, 35 by default; 0 never leads, only counts on its own page). A festival
    // whose collection isn't open yet takes no part. Jewish dates from Hebcal (the coordinator, 2026-11-11; the Jewish
    // songs page's countdown reads them); add each next year's before the last one passes.
    'festivals' => [
        ['key' => 'thanksgiving', 'collection' => 'thanksgiving-hymns', 'from' => '2026-11-26', 'days' => 1],
        ['key' => 'hanukkah', 'collection' => 'jewish-songs', 'from' => '2026-12-04', 'evening' => true, 'days' => 8, 'ahead' => 21],
        ['key' => 'christmas', 'collection' => 'christmas-carols', 'from' => '2026-12-25', 'days' => 1],
        ['key' => 'new-year', 'collection' => 'scottish-songs', 'from' => '2026-12-31', 'days' => 2],
        ['key' => 'tu-bishvat', 'collection' => 'jewish-songs', 'from' => '2027-01-22', 'evening' => true, 'days' => 1, 'ahead' => 0],
        ['key' => 'burns-night', 'collection' => 'scottish-songs', 'from' => '2027-01-25', 'days' => 1],
        ['key' => 'st-patricks-day', 'collection' => 'irish-songs', 'from' => '2027-03-17', 'days' => 1],
        ['key' => 'purim', 'collection' => 'jewish-songs', 'from' => '2027-03-22', 'evening' => true, 'days' => 1, 'ahead' => 0],
        ['key' => 'easter', 'collection' => 'easter-songs', 'from' => '2027-03-28', 'days' => 1],
        ['key' => 'passover', 'collection' => 'jewish-songs', 'from' => '2027-04-21', 'evening' => true, 'days' => 8, 'ahead' => 21],
        ['key' => 'independence-day', 'collection' => 'american-songs', 'from' => '2027-07-04', 'days' => 1],
        ['key' => 'thanksgiving', 'collection' => 'thanksgiving-hymns', 'from' => '2027-11-25', 'days' => 1],
        ['key' => 'hanukkah', 'collection' => 'jewish-songs', 'from' => '2027-12-24', 'evening' => true, 'days' => 8, 'ahead' => 21],
        ['key' => 'christmas', 'collection' => 'christmas-carols', 'from' => '2027-12-25', 'days' => 1],
        ['key' => 'new-year', 'collection' => 'scottish-songs', 'from' => '2027-12-31', 'days' => 2],
    ],

    'contact_email' => env('SITE_CONTACT_EMAIL') ?: 'contact@stackapella.com',

    // Where an organization writes about the partner offer (/for/organizations): the address Hillel
    // picks for outreach replies, once he has; the contact address until then.
    'partners_email' => env('SITE_PARTNERS_EMAIL') ?: 'contact@stackapella.com',

    // Legal questions, copyright notices and the coach agreement (Hillel, 2026-10-29): the legal
    // pages say legal@stackapella.com, shown as this.
    'legal_email' => env('SITE_LEGAL_EMAIL') ?: 'legal@stackapella.com',

    // The DMCA designated agent, as registered with the US Copyright Office (dmca.copyright.gov):
    // the copyright page names them, and sheet music import can't be switched on without them
    // (the doctor says). Empty until Hillel registers (launch checklist H17).
    'dmca_agent' => [
        'name' => (string) env('DMCA_AGENT_NAME', ''),
        'address' => (string) env('DMCA_AGENT_ADDRESS', ''),
        'phone' => (string) env('DMCA_AGENT_PHONE', ''),
        'email' => (string) env('DMCA_AGENT_EMAIL', ''),
    ],

    // Which wording of the import's rights confirmation the singer agreed to, kept with the song;
    // bumped (with the app's words) whenever the wording changes.
    'import_rights_version' => 'import-rights-v1',

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
