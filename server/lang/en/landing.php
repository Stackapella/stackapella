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
    // The label over the home page's seasonal link (the carols), so it reads as featured, not as a stray link.
    'seasonal' => 'This season',
    'dock' => ':song, the player',
    'alone' => 'Heard alone',
    // The demo's lanes (the UX review, 2026-11-23): a visible Solo toggle, and a link into the app to learn that part.
    'solo' => 'Solo',
    'solo_on' => 'Solo on',
    'learn_lane' => 'Learn it',
    // The hero (the approved blueprint, Hillel, 2026-11-23): the H1, its lede, and the first lesson, which needs no account.
    'hero' => [
        // Hillel, 2026-11-23: people search "sing harmony"; "make harmony" read like producer software.
        'before' => 'Sing harmony',
        'em' => 'with your voice.',
        'lede' => 'Harmony is hard to find on your own. Here a guide voice sings with you, so you hear how the parts fit and find yours.',
        'try' => 'Try the first lesson',
        'try_note' => 'No account needed',
    ],
    // Three ways in (the blueprint): who, the benefit, one next step. Make's go_choir shows while own_choir is on.
    'paths' => [
        'label' => 'Find your path.',
        'app' => 'in the app',
        // Who each path is for, then its goal, said the same everywhere (Hillel, 2026-11-23, the copy expert's words; the
        // rule in docs/design-rules.md). No badge on Learners: its line says free.
        'learn' => ['eyebrow' => 'Learners', 'badge' => '', 'who' => 'Learn harmony', 'what' => 'Free lessons with a guide voice, even on your own.', 'go' => 'See the free lessons'],
        'make' => ['eyebrow' => 'Singers', 'who' => 'Sing every part', 'what' => 'Record a whole song yourself.', 'go' => 'See how to layer your voice', 'go_choir' => 'Hear yourself as a choir'],
        'lead' => ['eyebrow' => 'Choir directors', 'who' => 'Lead a choir', 'what' => 'Give each singer their part and see who\'s ready.', 'go' => 'See tools for choir directors'],
    ],
    // Over the four pinned scenes: they are the solo performance.
    'solo_scene' => ['kick' => 'Sing every part', 'title_before' => 'Create your own', 'title_em' => 'a cappella performance.', 'body' => 'Sing each part yourself. As you record, hear your earlier takes play alongside you.'],
    // Under the home's first-lesson preview (a still card in the app's own words): it's an example.
    'lesson' => ['caption' => 'An example. In lesson 1, the app finds your own comfortable note as you sing.'],
    // A small optional card holding the real Game mode switch.
    'game_mode' => ['title' => 'Game mode', 'body' => 'Like it playful? Turn on Game mode for an arcade look.', 'tag' => 'Optional'],
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
        'before' => 'Find',
        'em' => 'your part.',
        'after' => '',
        'body' => 'Hear one part on its own, then all four together: that\'s how the parts fit.',
        'true' => 'Every song in the library opens this way.',
    ],
    'sing' => [
        'name' => 'Sing along',
        'before' => 'Learn it',
        'em' => 'by ear.',
        'after' => '',
        'chip' => 'You, singing along',
    ],
    'record' => [
        'name' => 'Record',
        'before' => 'Build',
        'em' => 'your performance.',
        'after' => '',
    ],
    'share' => [
        'name' => 'Share',
        'before' => 'The whole song',
        'em' => 'is you.',
        'after' => '',
        'card' => 'Four voices, every one yours.',
        // With own_choir: the scene's one way on to /your-choir.
        'choir' => 'Hear yourself as a choir: your first song is free',
    ],
    'free' => [
        'before' => 'New to harmony?',
        'em' => 'Start here.',
        'after' => '',
        'body' => 'Every course is always free. Learn by ear with short lessons and a guide voice to follow.',
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
        // The home's plans band (the blueprint): its own heading; /pricing keeps Free to learn, Plus for what you make.
        'title' => 'Learning is always free.',
        'title_more' => 'Plus for more ways to create.',
        'body' => 'Take any course for free. Plus adds more ways to create and collaborate.',
    ],
];
