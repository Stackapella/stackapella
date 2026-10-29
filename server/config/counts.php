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
        // The skins: app_open split by the skin in use, and the Game mode switch.
        'open_game', 'open_serious', 'game_on', 'game_off',
        // Plus extras.
        'sharpen_up', 'then_now', 'keep_song', 'fact_source',
        // Hum a tune.
        'hum_start', 'hum_click', 'hum_tap',
        // The studio (export_done stays the total of the three exports).
        'take_use', 'take_undo', 'track_add', 'track_rename', 'exp_song', 'exp_practice', 'exp_stems',
        // Onboarding: the asks that made an account, by reason, and the day-2 card.
        'share_note', 'card_named', 'su_progress', 'su_reminders', 'su_share', 'su_boards', 'card_day2',
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
    ],

    // Kinds only the server counts, never taken from counts.create: the mudeo email's link opened in
    // a browser, and someone who came from it making a first song or finishing a lesson (Hillel,
    // 2026-10-29: "Anonymous totals").
    'server_kinds' => ['mudeo_visit', 'mudeo_used'],
    // The onboarding funnel (App\Actions\Counts\Funnel, docs/onboarding-review.md section 4): each a
    // device's first, counted once per device by the app (f_site_start by the landing), in the
    // funnel's order. Arrivals are f_arrive plus f_arrive_start (one or the other, never both).
    'funnel' => [
        'f_site_start', 'f_arrive', 'f_arrive_start', 'f_mic', 'f_note', 'f_note_skip', 'f_lesson1', 'f_chord', 'f_lesson2', 'f_lesson3', 'f_unit1',
        'f_goal', 'f_install', 'f_name', 'f_account', 'f_share', 'f_remind', 'f_take', 'f_harmony', 'f_day2', 'f_week1', 'f_goal_met',
    ],

    // The asks, each counted as ask_<name>_shown (first time only) and ask_<name>_yes.
    'asks' => ['install', 'account', 'name', 'share', 'goal', 'remind'],

    // The A/B experiments, one at a time, oldest first. A device draws an arm at f_arrive while one
    // enrols (from `start` until `end` passes: set `end` once Admin > Funnel says the planned
    // sample is in, as the counts alone never stop it) and sends
    // "<id>-<arm>" with its funnel counts. `start` null: planned, not running. Read it from a week
    // after enrolment stops (day-2 and week-1 returns need it), never before.
    'experiments' => [
        ['id' => 'onb1', 'what' => 'The chord moment after lesson 1\'s note', 'arms' => ['a', 'b'], 'start' => null, 'end' => null, 'plannedPerArm' => 1500, 'primary' => 'f_lesson3', 'guardrail' => 'f_lesson2'],
    ],

    // At most this many of one kind, and this many kinds, in one request.
    'max_n' => 20,
    'max_events' => 20,

];
