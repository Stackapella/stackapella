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
    // Across everyone, part-minutes rendered a day: past it, new songs wait for tomorrow and the admins are told once.
    'daily_part_minutes' => 600,
    // An own song's singing across all its parts, at most (so one song fits one claim).
    'max_total_seconds' => 1200,
    // A claim kept alive by the worker (worker.choir.jobs.extend) never lasts past this from when it was claimed.
    'claim_limit_minutes' => 60,
    // A made song's full-quality links for Download (ownChoir.requests.download).
    'download_minutes' => 5,
    'clip_min_seconds' => 5,
    'clip_max_seconds' => 30,
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
