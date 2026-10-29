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

    'scoring_version' => (int) (env('BOARDS_SCORING_VERSION') ?: 1),

    // How many a board lists, above "your place".
    'top' => 10,

];
