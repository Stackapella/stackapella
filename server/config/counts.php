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
// What can be shared (sh:<surface>.<method>); each surface's source tag is share-<surface>.
$shareSurfaces = ['song', 'lesson', 'note', 'daily', 'invite', 'challenge', 'group', 'class', 'arrangement', 'warmup', 'app', 'embed', 'page-song', 'page-part', 'page-course', 'page-lesson', 'page-carols', 'page-collection'];
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
        // The improvement loop (docs/retention-plan.md, section 8): a device back on exactly day 1, 7, 14 or 30
        // after it first arrived, and once on any of days 1 to 6 (back_w1).
        'back_d1', 'back_d7', 'back_d14', 'back_d30', 'back_w1',
        // The thread back: a lesson's "come back" link and calendar reminder tapped, and the nudge's Not now.
        'back_link_tap', 'back_cal_tap', 'ask_nudge_not',
        // New and in season on Home: the card shown, and tapped.
        'home_new_shown', 'home_new_tap',
        // The paths the drop-off report reads (docs/retention-plan.md, section 8, "Measure"), each a device's first:
        // a song in Sing, from a lesson into a song and back, the creator, choir groups, a part page into the app,
        // and a share opened and sung.
        'sing_open', 'sing_listen', 'sing_along', 'sing_own', 'sing_scored', 'sing_kept', 'song_part2', 'song_part3',
        'lesson_song_open', 'lesson_song_back',
        'cr_open', 'cr_notes', 'cr_part2', 'cr_saved', 'cr_shared',
        'grp_start', 'grp_made', 'grp_link', 'grp_joined', 'grp_assigned', 'grp_sang',
        'part_sing', 'share_opened', 'invite_sung',
        // Weekly use of what section 2 found under-used, once a device a week (read against app_week).
        'use_library', 'use_song_course', 'use_course', 'use_workout', 'use_boss', 'use_studio', 'use_group', 'use_tools', 'remind_on',
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
        // Every other course (the Plus and song courses), lesson by lesson and step by step, as the free course is
        // (docs/retention-plan.md, section 8): cl_s:<lessonId> started, cl_d:<lessonId> finished,
        // cs_r:<lessonId>.<step> reached. Lesson ids are unique across every course, so no course id is needed.
        'cl_s' => 'course_lessons',
        'cl_d' => 'course_lessons',
        'cs_r' => 'course_steps',
        // Breadth (docs/retention-plan.md, section 8): a feature's first use on a device, ft:<feature>; and
        // how many features a device has tried, br:<n> (0 to 11), once a device a day beside app_day.
        'ft' => 'features',
        'br' => 'breadth',
        // A share, sh:<surface>.<method>: what was shared and how (config counts.share_surfaces and share_methods;
        // a free tool's surface is tool-<slug>).
        'sh' => 'shares',
        // A free tool's result reached, and its way into the app tapped: tool_result:<slug>, tool_try:<slug>.
        'tool_result' => 'tools',
        'tool_try' => 'tools',
        // The "try next" line (breadth's nudge): shown and tapped, by feature.
        'try_shown' => 'features',
        'try_tap' => 'features',
        // A unit's end offering a song to sing (docs/retention-plan.md, section 8, row 8): the offer shown, its button
        // tapped, a take kept from that visit, each by its place (song_offers below), once a device a place.
        'us_shown' => 'song_offers',
        'us_tap' => 'song_offers',
        'us_kept' => 'song_offers',
    ],

    // Where a song is offered at a unit's end (us_*): after units 1 to 4, and the wall. Units 5 to 8 join when theirs
    // are set (the retention agent, docs/retention-plan.md).
    'song_offers' => ['u1', 'u2', 'u3', 'u4', 'wall'],

    // The features breadth counts (ft:<feature>), each a device's first use.
    'features' => ['lesson', 'warmup', 'singalong', 'record', 'stack', 'whole', 'course', 'create', 'share', 'together', 'tool'],

    // What can be shared, and how (sh:<surface>.<method>); each surface's own source tag is share-<surface>
    // (a tool's, share-<slug>, as it already was).
    'share_surfaces' => $shareSurfaces,
    'share_methods' => ['sheet', 'copy', 'video', 'image', 'embed'],

    // Kinds only the server counts, never taken from counts.create: the mudeo email's link opened in
    // a browser, and someone who came from it making a first song or finishing a lesson (Hillel,
    // 2026-10-29: "Anonymous totals").
    'server_kinds' => ['mudeo_visit', 'mudeo_used', 'plus_started'],

    // A shared link's page opened, counted by the server as so:<surface> (docs/retention-plan.md, sharing): a shared
    // song's page, an invite to sing a part, Hillel's invite link, a choir group's join link, a challenge. By surface
    // only, never the token, the song or anyone.
    'shared_pages' => ['song', 'invite', 'app', 'group', 'challenge'],

    // Growth's sources (docs/admin-growth-plan.md): the campaign tags of our own ?ref= links, kept
    // as written. Any other tag is grouped (h-* "harmony", c-* "coach", else "other"), so no
    // harmony's or coach's id, and no one-off tag, is ever kept. A new campaign adds its tag here.
    'sources' => ['hn', 'mudeo', 'songs', 'song-page', 'part-page', 'carols', 'lesson-page', 'course-page', 'features-course', 'lesson-try', 'warmups', 'intervals', 'chords', 'voice-types', 'tools', 'tools-embed', 'share-range', 'share-pitch-matching', 'share-interval-ear-training', 'share-chord-ear-training', 'share-lesson', 'for-choir-directors', 'features-choirs', 'social', 'reddit', 'producthunt', 'newsletter', 'press',
        // The improvement loop: the verify email's next lesson, the day's part (/today), and the thread back.
        'welcome', 'today', 'back-link', 'back-calendar',
        // Each share surface's tag (share-lesson was already one; never folded, as every share- tag).
        ...array_values(array_diff(array_map(static fn (string $surface): string => 'share-'.$surface, $shareSurfaces), ['share-lesson'])),
    ],
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
        'acct_course_home', 'acct_course_lesson', 'acct_course_map', 'acct_course_page',
        // The thread back's nudge (docs/retention-plan.md; its Not now is ask_nudge_not in kinds).
        'nudge'],

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

    // The weekly drop-off report (App\Actions\Growth\Dropoffs; docs/retention-plan.md, "Detect"): a step fewer than
    // `floor` devices reached in the week is "too few to tell", never a drop; a drop is a pass-through more than
    // `margin` below its neighbours' median or its own four weeks before. Set with the coordinator at the first review.
    'dropoffs' => ['floor' => 30, 'margin' => 0.15],

    // Changes in what's counted that make the days before read differently (docs/growth-compare-plan.md,
    // section 6): Growth says so beside a comparison whose baseline lies before one. Never adjusted.
    'breaks' => [
        '2026-11-08' => ['kinds' => '*', 'note' => 'crawlers'],
        '2026-11-12' => [
            // The app first sent ft:<feature> and br:<n> on 12 November, about 04:05 UTC (build dde69063a; retention and
            // frontend). The fill-in from what a device already held is never sent as ft, and br waits for it.
            ['kinds' => ['ft:*', 'br:*'], 'note' => 'ft and br begin; 13 November is the first whole day'],
            // Until push167 (ed6f180c4, about 04:45 UTC; the fix 6a60dac25), a guest's view of the course wall also
            // counted as lesson 2's start (retention and the coordinator).
            ['kinds' => ['lesson_start', 'fl_s:1-match', 'fs_r:1-match.0'], 'note' => 'wall views no longer count as starts; 13 November is the first whole day'],
        ],
    ],

    // At most this many of one kind, and this many kinds, in one request.
    'max_n' => 20,
    'max_events' => 20,

];
