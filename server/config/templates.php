<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| The official library (docs/pricing-plan.md, "Songs: four free, the library on Plus")
|--------------------------------------------------------------------------
|
| php artisan app:templates:sync loads every official template into the catalogue (`templates` and
| the immutable `template_versions`), from the app's bundled files and, from M5, the Plus songs that
| live only on the server.
|
*/

return [

    // Where the official templates are read from. web/templates holds what the app bundles (until
    // M5, all 8); server/database/templates the Plus songs that never ship in the app's files.
    'folders' => [
        realpath(base_path('../web/templates')) ?: base_path('../web/templates'),
        database_path('templates'),
    ],

    // What's next shows a song's finishers (and what they also learned) only from this many.
    'finishers_to_show' => 20,

    // A song is among a song's "also learned" only when at least this many of its finishers finished
    // it too, so the list never tells what one or two people did.
    'also_learned_min' => 5,

    // The four free songs. Everything else is Plus, except the song of the month while it's free.
    'free' => ['amazing-grace', 'are-you-sleeping', 'michael-row', 'happy-birthday'],

    // The real-instrument samples on the media CDN (App\Support\Media\Instruments): off where none
    // are uploaded, so the apps use the synth and never look for an index that isn't there.
    'instruments' => (bool) env('INSTRUMENTS_ENABLED', false),

    // The courses' real-voice clips (App\Actions\Courses\CourseClips), synced with the templates.
    'clips' => realpath(base_path('../web/course/plus/clips.json')) ?: base_path('../web/course/plus/clips.json'),

    // The contract's schemas (the template format), for checking every file the server loads.
    'schemas' => realpath(base_path('../contract/schemas')) ?: base_path('../contract/schemas'),

];
