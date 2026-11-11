<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Anonymous usage counts (docs/decisions.md, "anonymous usage counts")
|--------------------------------------------------------------------------
|
| Hillel's "all in the app itself, no GA": how the app is used, as one number per day, kind of
| event, guest or account, and country. Nothing about who: no account, address, browser or cookie.
| Off on a self-hosted install unless its owner turns it on.
|
*/

$selfHosted = filter_var(env('SELF_HOSTED', false), FILTER_VALIDATE_BOOL);
$on = env('USAGE_COUNTS');

return [

    'enabled' => $on === null || $on === '' ? ! $selfHosted : filter_var($on, FILTER_VALIDATE_BOOL),

    // The only kinds counted; anything else is dropped.
    'kinds' => [
        'app_open', 'lesson_start', 'lesson_done', 'unit_done', 'song_created', 'take_recorded', 'share_created', 'harmonise_used', 'export_done', 'sign_up', 'result_shared', 'leaderboard_viewed', 'challenge_accepted',
        // Game mode: the day's challenge seen and sung.
        'daily_viewed', 'daily_sung',
        // Growth: the landing's own script, once a page load (a bot that runs no script isn't counted).
        'site_visit',
        // Every public page's load by its section only (App\Support\Site\SiteVisit; the footer carries it, site.js
        // sends it): no source, no path beyond the section.
        'sv_home', 'sv_courses', 'sv_course', 'sv_lesson', 'sv_tools', 'sv_songs', 'sv_part', 'sv_guides', 'sv_other',
        // A song part page's player played, once a page load at its first play (js/public/song-part-page.js):
        // no song, no part, no path, so guest playing ("Yes, play for guests") can be read against sv_part.
        'part_play',
        // Sheet music: a song made from an imported score (exports are exp_sheet, MusicXML or MIDI).
        'sheet_import',
        // People using the app (docs/admin-analytics-plan.md): each device once a UTC day, ISO week
        // and month, from the date it last counted (kept on the device, never an ID).
        'app_day', 'app_week', 'app_month',
        // The practice playlist (docs/practice-playlist-plan.md): a set started, Repeat my part used.
        'set_play', 'set_repeat',
        // The skins: app_open split by the skin in use, and the Game mode switch.
        'open_game', 'open_serious', 'game_on', 'game_off',
        // Plus extras.
        'sharpen_up', 'then_now', 'keep_song', 'fact_source',
        // Hum a tune.
        'hum_start', 'hum_click', 'hum_tap', 'hum_found', 'hum_saved',
        // The studio (export_done stays the total of the three exports).
        'take_use', 'take_undo', 'track_add', 'track_rename', 'exp_song', 'exp_practice', 'exp_stems', 'exp_sheet',
        // Onboarding: the asks that made an account, by reason, and the day-2 card.
        'share_note', 'card_named', 'su_progress', 'su_reminders', 'su_share', 'su_boards', 'card_day2',
        // The account nudges (docs/account-nudges-plan.md): an account made, by what asked for it,
        // and each ask's "Not now" (its shown and yes are asks below).
        // A school class code redeemed (docs/school-offer-plan.md), kept with where the device came
        // from, so an outreach organization's link can be seen to bring codes in.
        'school_redeemed',
        'su_week', 'su_songs', 'su_unit', 'su_risk', 'su_groups', 'su_friends', 'su_voice', 'su_plus', 'su_template', 'su_sheet', 'su_settings', 'su_daily', 'su_school',
        'ask_acct_week_not', 'ask_acct_songs_not', 'ask_acct_unit_not', 'ask_acct_risk_not', 'ask_acct_settings_not', 'ask_acct_course_not',
        // Home after week one (docs/home-returning-plan.md, experiment ret1): each Today line shown and tapped.
        'home_today_gap_shown', 'home_today_gap_tap', 'home_today_done_shown', 'home_today_done_song_tap', 'home_today_done_course_tap',
        'home_today_stuck_shown', 'home_today_stuck_tap', 'home_today_daily_shown', 'home_today_daily_tap',
        // Melody first: what the app suggested, and whether the singer changed it.
        'sug_melody', 'sug_part', 'sug_switch',
    ],

    // Kinds with a detail, "<prefix>:<id>", counted only when the id is a real one (a course in
    // web/course/plus/courses.json, a lesson in web/course/course.json): a Plus course's lessons
    // started and finished, and the free course's, lesson by lesson.
    'detailed' => [
        'pl_s' => 'plus_courses',
        'pl_d' => 'plus_courses',
        'fl_s' => 'free_lessons',
        'fl_d' => 'free_lessons',
        // A free-course lesson's step reached, "fs_r:<lessonId>.<step>" (step from 0, as the lesson
        // lists them, a variant's too): where a lesson's singers stop (2026-11-11, the courses lead).
        'fs_r' => 'free_steps',
    ],

    // Kinds only the server counts, never taken from counts.create: the mudeo email's link opened in
    // a browser, and someone who came from it making a first song or finishing a lesson (Hillel,
    // 2026-10-29: "Anonymous totals").
    'server_kinds' => ['mudeo_visit', 'mudeo_used', 'plus_started'],

    // Growth's sources (docs/admin-growth-plan.md): the campaign tags of our own ?ref= links, kept
    // as written. Any other tag is grouped (h-* "harmony", c-* "coach", else "other"), so no
    // harmony's or coach's id, and no one-off tag, is ever kept. A new campaign adds its tag here.
    'sources' => ['hn', 'mudeo', 'songs', 'song-page', 'part-page', 'carols', 'lesson-page', 'course-page', 'features-course', 'lesson-try', 'warmups', 'intervals', 'chords', 'voice-types', 'tools', 'tools-embed', 'share-range', 'share-pitch-matching', 'share-interval-ear-training', 'share-chord-ear-training', 'share-lesson', 'for-choir-directors', 'features-choirs', 'social', 'reddit', 'producthunt', 'newsletter', 'press'],
    // The onboarding funnel (App\Actions\Counts\Funnel, docs/onboarding-review.md section 4): each a
    // device's first, counted once per device by the app (f_site_start by the landing), in the
    // funnel's order. Arrivals are f_arrive plus f_arrive_start (one or the other, never both).
    'funnel' => [
        'f_site_start', 'f_arrive', 'f_arrive_start', 'f_mic', 'f_note', 'f_note_skip', 'f_lesson1', 'f_chord', 'f_lesson2', 'f_lesson3', 'f_unit1',
        'f_goal', 'f_install', 'f_name', 'f_account', 'f_share', 'f_remind', 'f_take', 'f_harmony', 'f_day2', 'f_week1', 'f_goal_met',
        // Experiments' longer reads (docs/experiments-plan.md, slice 4): back on day 7, and Plus
        // started on this device (the server's plus_started can't know a device's arm).
        'f_day7', 'f_plus',
        // Back on any of days 14 to 20 (keep1's guardrail).
        'f_day14',
        // A first lesson finished with a star in any course, the free one included: where a free
        // tool's or a page's first lesson in another course leads (the growth pull's sources).
        'f_course_lesson',
    ],

    // The asks, each counted as ask_<name>_shown (first time only) and ask_<name>_yes.
    // acct_course_<place>: the course's account ask by where it was met (the home's lesson card, a
    // lesson, the course map, a course's page), beside acct_course's total; its Not now is the total's alone.
    'asks' => ['install', 'account', 'name', 'share', 'goal', 'remind', 'acct_week', 'acct_songs', 'acct_unit', 'acct_risk', 'acct_settings', 'acct_course',
        'acct_course_home', 'acct_course_lesson', 'acct_course_map', 'acct_course_page'],

    // The A/B experiments are in the registry (the experiments table, Admin > Experiments;
    // docs/experiments-plan.md), not here. A test is read by, and guarded with, funnel steps.

    // Well-known crawlers, matched in the user agent (any case; App\Support\Stats\Crawler): their counts are
    // dropped, every kind alike, and the agent is never kept. Names only, never a bare "bot" (a phone
    // brand's agent can hold it).
    'crawlers' => [
        'googlebot', 'google-inspectiontool', 'googleother', 'adsbot-google', 'mediapartners-google', 'storebot-google', 'apis-google',
        'bingbot', 'bingpreview', 'adidxbot', 'msnbot', 'duckduckbot', 'yandexbot', 'baiduspider', 'applebot', 'seznambot', 'petalbot',
        'ahrefsbot', 'semrushbot', 'mj12bot', 'dotbot', 'rogerbot', 'screaming frog', 'bytespider', 'gptbot', 'oai-searchbot', 'chatgpt-user',
        'claudebot', 'claude-user', 'claude-searchbot', 'perplexitybot', 'ccbot', 'amazonbot', 'meta-externalagent', 'facebookexternalhit',
        'twitterbot', 'linkedinbot', 'slackbot', 'discordbot', 'telegrambot', 'whatsapp', 'pinterestbot', 'chrome-lighthouse',
    ],

    // Changes in what's counted that make the days before read differently (docs/growth-compare-plan.md,
    // section 6): Growth says so beside a comparison whose baseline lies before one. Never adjusted.
    'breaks' => [
        '2026-11-08' => ['kinds' => '*', 'note' => 'crawlers'],
    ],

    // At most this many of one kind, and this many kinds, in one request.
    'max_n' => 20,
    'max_events' => 20,

];
