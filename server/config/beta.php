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

    // Lend your voice (docs/lend-your-voice-plan.md): volunteers accept their own agreement
    // (resources/legal/en/lend-your-voice.md) instead of this one; a claim is held this long, says
    // it's ending (in the app) this many days in, and someone holds at most this many at once.
    'volunteer_agreement_version' => 2,
    'claim_days' => 14,
    'claim_remind_after_days' => 10,
    'claims_at_once' => 3,

    // A take: at most 10 minutes, mono 16-bit WAV, at a sample rate a browser records at.
    'max_seconds' => 600,
    'sample_rates' => [16000, 22050, 24000, 32000, 44100, 48000, 88200, 96000],

    // Guides are levelled to about this loudness (RMS, dBFS) when published.
    'guide_rms_dbfs' => -20.0,

    // A guide's AAC bitrate (kbps, mono). 128 until Hillel hears the 64 and 48 pairs; app:guides:reencode brings the
    // published guides to it from their masters, in place.
    'guide_kbps' => 128,

    // Rejected takes' masters are deleted after this many days; accepted ones are kept.
    'rejected_master_days' => 90,

];
