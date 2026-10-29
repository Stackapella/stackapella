<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Sharing (docs/backend-plan.md D6, docs/more-features-plan.md feature 4)
|--------------------------------------------------------------------------
|
| Harmonies shared by link, the videos made from them, and reports. The limits on how often are
| in config/limits.php (`shares`, `share-videos`, `reports`).
|
*/

return [

    // The mix check (docs/trust-and-safety-plan.md, "Uploads and shared audio").
    'max_seconds' => 600,
    'tail_seconds' => 15,
    'sample_rates' => [8000, 48000],

    // Reports from this many different people hide a page until Hillel looks.
    'reports_to_hide' => 3,

    // An upheld report lowers the owner to trust level 1 for this long.
    'upheld_days' => 30,

    'videos' => [
        // Renders at once, across everyone: the global cap protects the bill. Below the two
        // `long` workers (.do/app.yaml), so a take or a mix never waits behind videos.
        'concurrent' => 1,
        'keep_days' => 7,
        // A video that can't start within this long (paused, or a long queue) fails as timed_out.
        'wait_minutes' => 60,
        'min_seconds' => 3,
        'max_seconds' => 60,
        'end_card_seconds' => 2,
        'fps' => 30,
    ],

    // Our font, for the share card and the video (resources/fonts, SIL Open Font License).
    'fonts' => [
        'bold' => resource_path('fonts/Figtree-Bold.ttf'),
        'medium' => resource_path('fonts/Figtree-Medium.ttf'),
    ],

];
