<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Hear yourself as a choir (docs/ai-voices-build-plan.md, track B phase 3, behind own_choir)
|--------------------------------------------------------------------------
|
| The pricing Hillel is to set (proposed: one free song, 20 a month with Plus, a daily cap of 5), the clip's length,
| and the worker's claim. A failed song gives its place back.
|
*/

return [
    'free_songs' => 1,
    // The GPU review (2026-11-21): paid Plus counts part-minutes (each part's length, summed), Plus not paid for (a
    // trial, a school code, a coach, a grant) a few songs, each over a rolling 30 days from when a song reached the
    // renderer. Hillel sets the numbers; these are the review's defaults.
    'plus_minutes' => 60,
    'comped_songs' => 3,
    // Where dots in an address's name make no new address (one mailbox): the first is the one they're counted as.
    'dotless_domains' => ['gmail.com', 'googlemail.com'],
    'window_days' => 30,
    'daily' => 5,

    // The tune every choir clip sings, whatever song is being made (Hillel, 2026-11-23: "let's go with Happy Birthday"):
    // voice-capture, a hidden one-part template (Happy Birthday once, then For He's a Jolly Good Fellow once, about 30
    // seconds), listed nowhere; a singer may sing the song's own tune instead.
    'clip_template' => 'voice-capture',
    // The tunes a singer may choose for their clip (Hillel, 2026-11-23), in the order shown, voice-capture (shown as Happy
    // Birthday) first, each with the beat it stops on (a phrase start, about 30 seconds in; null: the whole tune).
    'clip_templates' => ['voice-capture' => null, 'silent-night' => 36, 'amazing-grace' => 36, 'joy-to-the-world' => 63],
    // The free song is the song they sang (Hillel, 2026-11-23: "more expected"): each clip tune's song, itself unless
    // named here.
    'clip_songs' => ['voice-capture' => 'happy-birthday'],
    // A clip tune whose first section is a whole song: that stretch is the song's lead, the song moved by keyShift
    // semitones to the key it's in there (voice-capture's Happy Birthday is in F, the song in G; 2026-11-23).
    'clip_leads' => ['voice-capture' => ['song' => 'happy-birthday', 'beats' => 25, 'keyShift' => -2]],
    // Across everyone, part-minutes rendered a day: past it, new songs wait for tomorrow and the admins are told once.
    'daily_part_minutes' => 600,
    // A remake at fine quality (64 steps) costs its real GPU time: 1.92x measured, rounded (Hillel, 2026-11-23: "make
    // sure limits align with our costs").
    'fine_factor' => 2.0,
    // An own song's singing across all its parts, at most (so one song fits one claim).
    'max_total_seconds' => 1200,
    // A claim kept alive by the worker (worker.choir.jobs.extend) never lasts past this from when it was claimed.
    'claim_limit_minutes' => 60,
    // A made song's full-quality links for Download (ownChoir.requests.download).
    'download_minutes' => 5,
    // A choir with friends (docs/choir-together-plan.md): the coordinator's defaults until Hillel confirms them
    // (2026-11-22). Friends a song can have (never more than its parts minus one), an invite's days, invites an
    // account makes a day, and choirs one person joins a day.
    'together' => [
        'free_friends' => 1,
        'plus_friends' => 7,
        'invite_days' => 14,
        'invites_a_day' => 30,
        'joins_a_day' => 5,
    ],
    'clip_min_seconds' => 5,
    // The voice-capture tune is about 31 seconds, with a lead-in and a tail about 32.5 (2026-11-23).
    'clip_max_seconds' => 35,
    'claim_minutes' => 20,
    'max_tries' => 3,
    // How long a ready part's private link lasts (the app keeps the part to play offline).
    'link_minutes' => 120,
    // A kept voice is deleted after this many months unused (Hillel's 12; the lawyer confirms the period).
    'voice_months' => 12,
    // A prepared reference's metadata, at most (the renderer's own JSON).
    'meta_bytes' => 65536,
    // What one song may cost the renderer (the security review): its parts at most, and each part's length in seconds.
    'max_parts' => 8,
    'max_part_seconds' => 300,
];
