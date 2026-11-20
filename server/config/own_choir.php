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
    'plus_month' => 20,
    'daily' => 5,
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
