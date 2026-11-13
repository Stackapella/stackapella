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

    // The songs' stories: <slug>.md and <language>/<slug>.md, beside the songs (App\Support\Pages\SongStories).
    'stories' => database_path('templates/stories'),

    // The songs' line meanings: meaning/<slug>.json, beside the songs (App\Support\Pages\SongMeanings).
    'meanings' => database_path('templates/meaning'),

    // Each song's whole-song audio on the media CDN (App\Support\SongAudio\SongAudioFiles): the committed manifest
    // app:song-audio:upload writes once the renders are up.
    'song_audio' => resource_path('data/song_audio.json'),

    // Where a story's parse is kept (by its text's hash and the parser's version): the file store, so a deploy's
    // first visitor parses it once. Tests keep it in memory (phpunit.xml), so none passes on a parse left from another run.
    'stories_cache' => env('STORIES_CACHE', 'file'),

    // What's new, newest first (App\Support\Site\WhatsNew).
    'whats_new' => resource_path('whats-new/en.json'),

];
