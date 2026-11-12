<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Part leaderboards (docs/decisions.md, "part leaderboards")
|--------------------------------------------------------------------------
|
| The scoring version the boards rank (web/js/scoring.js's, which the app sends with each score):
| scores from another version never mix with these. A new one starts fresh boards, or a rescore of
| the pinned takes carries the old scores across (docs/runbook.md, "Rescoring the boards").
|
*/

return [

    // 2 since web/js/scoring.js's easier scoring (de858090c, 2026-11-12): the default followed it late, and until it did
    // every web score was refused as scoring_outdated. Change both together (a test holds them equal).
    'scoring_version' => (int) (env('BOARDS_SCORING_VERSION') ?: 2),

    // How many a board lists, above "your place".
    'top' => 10,

];
