<?php

declare(strict_types=1);

// The landing's own words (the home page, pages/landing.blade.php; the design chosen on 2026-11-16). Everything the page says
// that the landing, the plans or the site already say is read from their keys, so it can't drift from them; only what
// is new is here. Spanish: lang/es/landing.json.
return [
    'title' => 'Harmony, made by you | Stackapella',
    'description' => 'Hear Amazing Grace in four voices, take one of them, and see how a harmony becomes yours: learn a part, sing it over the others, share the whole song.',
    // The top line, where it has words of its own.
    'skip' => 'Skip to the song',
    'home' => 'Stackapella, home',
    'pages' => 'Pages',
    // The first scene.
    'lede' => 'Hear four voices, then take one.',
    'lede_more' => 'Learn a part, sing it over the others, and share a song where every voice is yours.',
    'lede_free' => 'Learning is free.',
    'play' => 'Play :song',
    'stop' => 'Stop',
    // Under Play's words: what a press commits to (the length is both times through).
    'play_meta' => ':count voices · :length',
    'hint' => 'Tap a voice to hear it alone',
    'scroll' => 'Scroll to see how it works',
    // The stage.
    'stage' => ':song in four voices',
    'voices' => 'The four voices',
    'learn_part' => 'I sing this: learn the :part part in the app',
    // The way to learn the voice last heard alone (the melody's until one is).
    'learn' => 'Learn the :part part',
    'dock' => ':song, the player',
    'alone' => 'Heard alone',
    'sung_by' => ':parts sung by :name',
    'and' => ' and ',
    'time' => 'Time',
    'scenes' => 'The story, scene by scene',
    'scene' => 'Scene :n of :count',
    // What a screen reader is told as the song plays.
    'live' => [
        'playing' => 'Playing :song.',
        'stopped' => 'Stopped.',
        'alone' => 'The :part alone.',
        'all' => 'All four voices.',
    ],
    // While the recorded voices download (a press waits for them; the page adds how far, "37%"), and when they can't.
    'loading' => 'Loading the voices…',
    'failed' => 'Couldn\'t load the voices. Try again.',
    // What the stage says as the song builds: the first voice, each voice as it joins, then all four (the second time
    // through), and a voice heard alone.
    'build' => [
        'first' => ':part starts',
        'join' => ':part joins',
        'all' => 'All four together. Tap a voice to hear it alone.',
        'alone' => 'The :part alone. Tap it again for all four.',
    ],
    'step' => ':n of 4',
    // Game mode's words for the same things (the facts never differ): Play's words, and a scene's place.
    'game' => [
        'play' => 'Press start',
        'step' => 'Level :n of 4',
    ],
    // Each scene's heading in three pieces: before its key phrase, the key phrase, after it.
    'hear' => [
        'name' => 'Hear',
        'before' => 'Hear',
        'em' => 'your part',
        'after' => 'on its own.',
        'body' => 'Tap a voice and the others step back. Tap it again and all four return, from the same spot.',
        'true' => 'Every song in the library opens this way.',
    ],
    'sing' => [
        'name' => 'Sing along',
        'before' => 'Learn it from a',
        'em' => 'real singer.',
        'after' => '',
        'chip' => 'You, singing along',
    ],
    'record' => [
        'name' => 'Record',
        'before' => 'Record each part over the others,',
        'em' => 'in time.',
        'after' => '',
    ],
    'share' => [
        'name' => 'Share',
        'before' => 'The whole song',
        'em' => 'is you.',
        'after' => '',
        'card' => 'Four voices, every one yours.',
    ],
    'free' => [
        'before' => 'Every course',
        'em' => 'is free.',
        'after' => '',
        'body' => 'Short lessons with a guide voice, from your first note to barbershop. Hear every song without an account.',
        'courses' => ['Courses, every one free', 'Pitch, harmony, vibrato, barbershop and more.'],
        'songs' => ['Songs to learn, part by part', 'Hymns, carols, folk songs and rounds.'],
        'tools' => ['Free tools, no account', 'A tuner, a metronome, a range test, a drone.'],
        'start' => 'Start lesson one',
    ],
    'choir' => [
        'before' => 'For one voice, or a',
        'em' => 'whole choir.',
        'after' => '',
        'body' => 'Give each singer a part, and see who has started and who has recorded. They learn at home, so rehearsal is for singing together.',
        // Shown while starting a group takes Plus (the plus_teams switch).
        'plus' => 'Starting a choir group is part of Plus. Everyone you invite joins free.',
        'covered' => '4 of 4 parts covered · 2 recorded',
        'recorded' => 'Recorded',
        'learning' => 'Learning',
        'waiting' => 'Not started',
        'note' => 'A choir group in the app. Sample names.',
        // Sample singers, shown by name and initials only.
        'singers' => ['Priya Shah', 'Sam Lee', 'Kim Ode', 'Tom Okafor'],
    ],
    'plans' => [
        'body' => 'Learning never needs a card. Plus is for arrangers and creators.',
    ],
];
