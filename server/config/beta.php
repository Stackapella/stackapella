<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| The beta singers (docs/beta-singers-plan.md)
|--------------------------------------------------------------------------
*/

return [

    // The beta agreement's version (resources/legal/en/beta-agreement.md). A singer accepts this
    // one before sending a take; a new version asks again.
    'agreement_version' => 1,

    // A take: at most 10 minutes, mono 16-bit WAV, at a sample rate a browser records at.
    'max_seconds' => 600,
    'sample_rates' => [16000, 22050, 24000, 32000, 44100, 48000, 88200, 96000],

    // Guides are levelled to about this loudness (RMS, dBFS) when published.
    'guide_rms_dbfs' => -20.0,

    // Rejected takes' masters are deleted after this many days; accepted ones are kept.
    'rejected_master_days' => 90,

];
