<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| The content
|--------------------------------------------------------------------------
| Where the content under CONTENT-LICENSE is read from (docs/open-source-plan.md, 3.1). An install
| that may not use it deletes the folders, and the app runs without them: no guides, no what's new
| entries, and no songs until it adds its own (the songs' folders are config/templates.php's).
*/

return [

    // The guides: <language>/<slug>.md (App\Support\Legal\LegalText).
    'guides' => resource_path('guides'),

    // What's new, newest first (App\Support\Site\WhatsNew).
    'whats_new' => resource_path('whats-new/en.json'),

];
