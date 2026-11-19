<?php

declare(strict_types=1);

/*
 * A song's versions (Hillel, 2026-11-18, "One song, US/UK switch"): where the UK sings a carol to another tune or
 * harmony, that version is its own song file linked to the song (`versionOf`: {song, name}), and the song keeps one page
 * with a small switch. App\Support\Templates\SongVersions reads it.
 */
return [
    // The name a song's own file goes by beside its versions: the switch's first choice.
    'base' => 'us',

    // The versions people start on, by where they are (Cloudflare's country, read per request and never stored): the UK
    // and the Crown Dependencies, whose churches sing from the same books. Anyone may switch, and a pick is kept on the
    // device.
    'starts' => [
        'uk' => ['GB', 'IM', 'JE', 'GG'],
    ],
];
