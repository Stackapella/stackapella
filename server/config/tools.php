<?php

declare(strict_types=1);

// The free tools (docs/tools-plan.md): one list of their addresses, /tools/<slug>, that every part
// reads, so none can drift. A slug here counts its try-the-app link (?ref=tool-<slug>) and has an
// install manifest; marketing's SitePages lists those live, and a test checks both, and the install
// names (lang/en/tools.php), against it. Every tool planned, not only those live, so a link counts
// from the day it exists.
return [
    'slugs' => [
        'tuner', 'metronome', 'vocal-range-test', 'pitch-pipe', 'tuning-forks', 'drone',
        'audio-latency-test', 'interval-ear-training', 'chord-ear-training', 'tap-tempo',
        'mic-test', 'pitch-matching', 'vocal-key-finder', 'just-intonation-tuner', 'note-frequency-chart',
        'chord-transposer', 'four-part-harmony',
        // Variants: a live tool with a preset.
        'ukulele-tuner', 'violin-tuner', 'voice-tuner', 'tanpura', '432-hz-tuning-fork',
        'guitar-tuner', 'bass-tuner', 'cello-tuner', 'viola-tuner', 'mandolin-tuner', 'banjo-tuner',
    ],

    // The tools that open the mic on a tap (the pitch is worked out on the device, nothing recorded or
    // sent): only their pages may ask for it (SecurityHeaders' Permissions-Policy).
    // The tools other sites may embed (docs/seo-growth-plan.md, item 2: /embed/tools/<slug>), free,
    // with no cookies and no tracking but our anonymous counts by the embedding site's host.
    'embeddable' => ['tuner', 'metronome', 'drone', 'pitch-pipe'],

    'listening' => ['tuner', 'vocal-range-test', 'audio-latency-test', 'mic-test', 'pitch-matching', 'just-intonation-tuner', 'ukulele-tuner', 'violin-tuner', 'voice-tuner', 'guitar-tuner', 'bass-tuner', 'cello-tuner', 'viola-tuner', 'mandolin-tuner', 'banjo-tuner'],
];
