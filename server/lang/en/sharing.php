<?php

declare(strict_types=1);

// Shared harmonies: their pages, cards and videos (contract/api.md, "Sharing").
return [
    'untitled' => 'Untitled song',
    'a_singer' => 'A Stackapella singer',
    // Someone who hid their profile, wherever others see them.
    'anonymous' => 'Anonymous',
    'one_part' => 'One part',
    'sung_by' => 'Sung by :names',
    // With instrument parts: every track an instrument, or some of each.
    'played_by' => 'Played by :names',
    'sung_and_played_by' => 'Sung and played by :names',
    'and' => ':first and :last',
    'credits' => [
        'words' => 'Words: :name',
        'tune' => 'Tune: :name',
        'arranged' => 'Arranged by Stackapella',
    ],
    // A song's link card (SongCards): its parts, under the title (what the link does is the
    // site's words, site.meta.app.card).
    'song_card' => [
        'parts' => 'Harmony in :count parts',
    ],
    // The card's and the video's song line.
    'song_line' => ':song, arranged by Stackapella',
    'just_part' => ':part, from :song',
    'description' => ':names sang :song in harmony on Stackapella.',
    'description_part' => ':names sang the :part part of :song on Stackapella.',
    'description_own' => ':names sang it in harmony on Stackapella.',
    'description_played' => ':names played :song on Stackapella.',
    'description_part_played' => ':names played the :part part of :song on Stackapella.',
    'description_own_played' => ':names played it on Stackapella.',
    'description_both' => ':names sang and played :song on Stackapella.',
    'description_own_both' => ':names sang and played it on Stackapella.',
    'image_alt' => ':title, sung by :names',
    'image_alt_played' => ':title, played by :names',
    'image_alt_both' => ':title, sung and played by :names',
    'video' => [
        'made_with' => 'Made with Stackapella',
    ],
    // The shared page (/h/<id>), its embed, and their Report form.
    'page' => [
        'eyebrow' => 'A harmony on Stackapella',
        'play' => 'Play',
        'pause' => 'Pause',
        'listen' => 'Listen',
        'learn' => 'Learn this harmony',
        'make' => 'Make your own harmony',
        'tagline' => 'Stackapella: harmony, made by you.',
        'voices' => 'Guide voices: :names',
        'report' => 'Report',
        'report_title' => 'Report this harmony',
        'report_why' => 'What\'s wrong?',
        'reasons' => [
            'copyright' => 'It uses my work without my permission',
            'offensive' => 'It\'s offensive or hateful',
            'spam' => 'It\'s spam',
            'personal' => 'It shares someone\'s private information, or records someone without their OK',
            'other' => 'Something else',
        ],
        'report_note' => 'Anything we should know (optional)',
        'report_send' => 'Send report',
        'cancel' => 'Cancel',
        'report_sent' => 'Thanks. We\'ll take a look.',
        'report_failed' => 'Couldn\'t send just now. Try again in a moment.',
        'missing' => 'This harmony isn\'t here',
        'missing_body' => 'It may have been made private, or deleted.',
        'removed' => 'This harmony was removed',
        'removed_body' => 'It was taken down and can\'t be played.',
    ],
];
