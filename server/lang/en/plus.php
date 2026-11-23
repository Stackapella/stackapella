<?php

declare(strict_types=1);

// Plus's features, one list every page reads (App\Support\Site\PlusFeatures; Hillel's approved marketing review,
// 2026-11-22): marketing owns the words. Each item is [gain, fact] and its short name for the one-line form, in this
// order; `choir` shows only while own_choir is on, `together` only while plus_teams is on. :storage, :minutes and
// :songs are filled from config. Prices stay outside the list.
return [
    'items' => [
        'choir' => ['Your own AI choir.', 'Every part of a song sung by AI in a voice like yours: :minutes minutes a month, about :songs songs, each one yours to download.'],
        'together' => ['Start choir groups.', 'Invite friends to your songs, and give each singer in a group a part. Everyone you invite joins free.'],
        'storage' => ['Room for every take.', ':storage for your recordings.'],
        'stems' => ['Every part as its own file.', 'Each voice as its own audio file, and the mix as MP3.'],
        'private' => ['Unlimited private arrangements.', 'Shared by link or with your choir.'],
    ],
    // With choir seats on, a seat in any choir group comes with Plus (seats.php's words).
    'together_seats' => 'Invite friends to sing on your songs, and they join free. Plus is also your seat in any choir group.',
    'short' => [
        'choir' => 'about :songs songs a month in a voice like yours',
        'together' => 'singing together',
        'storage' => 'more storage',
        'stems' => 'each voice as its own file',
        'private' => 'private arrangements',
    ],
    'sentence' => 'Plus adds :list.',
    'and' => ' and ',
];
