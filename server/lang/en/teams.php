<?php

declare(strict_types=1);

/*
 * "Teaming up is Plus" (docs/pricing-plan.md, top section): what each page says while the plus_teams switch is on, keyed
 * by the full key it changes (App\Support\Site\Teams). Learning alone stays free; whoever starts a shared song or a choir
 * group needs Plus; everyone they invite joins free; listening stays free. Only the changed words are here: a list's item
 * by its index, a page's words by their keys.
 */
return [
    // The plan cards (landing and /pricing).
    'pages.landing.plans_free_list' => [3 => 'Join any song or choir group you\'re invited to'],
    'pages.landing.plans_plus_list' => [3 => ['Sing together.', 'Invite friends to sing on your songs and start choir groups. Everyone you invite joins free.']],
    'pages.landing.plans_choirs_link' => 'singers join free, and learning tracks for your choir',
    // The landing's questions.
    'pages.landing.faq' => ['free' => [1 => 'Yes. Learning is free, with no card and no time limit: every course, every song with a free account, unlimited workouts, your history, recording every part, and joining any song or choir group you\'re invited to. You can start without an account. Plus, from :price a month, lets you invite friends to sing and start choir groups, and adds more storage, MP3 and stems, and unlimited private arrangements.']],
    // /pricing.
    'pages.pricing.rows' => [
        'singers' => ['Inviting friends to sing on your songs', 'No', ':plus'],
        'groups' => ['Choir groups (18 and over)', 'Join free', 'Start and join'],
    ],
    'pages.pricing.faq' => [3 => [1 => 'Nothing for your singers: they join free. Starting the group takes Plus. We also make learning tracks of a choir\'s own songs, and your first song is free. See For choirs.']],
    // Help.
    'pages.help.pages.plus-and-billing.sections' => [0 => [1 => [2 => 'Record every part, polish the mix, export as WAV, and share links and videos. You can join any song or choir group you\'re invited to; inviting friends to sing on your songs and starting a choir group take Plus, and everyone you invite joins free.']]],
    'pages.help.pages.groups.sections' => [0 => [1 => [0 => 'A choir director starts a group, sends the singers its link, shares songs, and says who sings which part. Starting a group takes Plus; every singer joins free.']]],
    // The choirs feature page.
    'site.features.pages.choirs' => [
        'description' => 'Directors share songs, give each singer a part, and see who has started and recorded it. Members learn at home with guide voices and join free. 18 and over.',
        'sections' => [2 => ['body' => 'Starting a choir group takes Plus; every member joins free. Each member learns the group\'s songs at home with a free account.']],
    ],
    // The landing's together section.
    'site.teams.items' => [2 => [1 => 'Start one with Plus and your singers join free: share songs with your choir, give each singer a part, and see who has started and who has recorded it. You never see their recordings or contact details. For singers 18 and over.']],
    // The carols page.
    'site.carols.choirs_body' => 'Start a choir group with Plus and your singers join free: one link brings them in, you give each their part, and you see who has started and recorded it before rehearsal.',
    // For choir directors.
    'site.for.pages.choir-directors' => ['faq' => [0 => [1 => 'Your first song\'s learning tracks are free, and your singers never pay: they join your group free. Starting the group takes Plus.']]],
    // The compare pages' "Is Stackapella free?" (each page's third question), the same answer on every page.
    'site.compare.pages.howli' => ['faq' => [2 => [1 => 'Yes: every course and every song in the library, with a free account, and unlimited workouts, your history, and joining any song or choir group you\'re invited to. Plus, for :price_month a month or :price_year a year, lets you invite friends to sing and start choir groups, and adds more storage, MP3 and stems, and unlimited private arrangements.']]],
    'site.compare.pages.harmony-helper' => ['faq' => [2 => [1 => 'Yes: every course and every song in the library, with a free account, and unlimited workouts, your history, and joining any song or choir group you\'re invited to. Plus, for :price_month a month or :price_year a year, lets you invite friends to sing and start choir groups, and adds more storage, MP3 and stems, and unlimited private arrangements.']]],
    'site.compare.pages.sing-harmonies' => ['faq' => [2 => [1 => 'Yes: every course and every song in the library, with a free account, and unlimited workouts, your history, and joining any song or choir group you\'re invited to. Plus, for :price_month a month or :price_year a year, lets you invite friends to sing and start choir groups, and adds more storage, MP3 and stems, and unlimited private arrangements.']]],
    'site.compare.pages.acapella' => ['faq' => [2 => [1 => 'Yes: every course and every song in the library, with a free account, and unlimited workouts, your history, and joining any song or choir group you\'re invited to. Plus, for :price_month a month or :price_year a year, lets you invite friends to sing and start choir groups, and adds more storage, MP3 and stems, and unlimited private arrangements.']]],
    'site.compare.pages.yousician' => ['faq' => [2 => [1 => 'Yes: every course and every song in the library, with a free account, and unlimited workouts, your history, and joining any song or choir group you\'re invited to. Plus, for :price_month a month or :price_year a year, lets you invite friends to sing and start choir groups, and adds more storage, MP3 and stems, and unlimited private arrangements.']]],
    'site.compare.pages.smule' => ['faq' => [2 => [1 => 'Yes: every course and every song in the library, with a free account, and unlimited workouts, your history, and joining any song or choir group you\'re invited to. Plus, for :price_month a month or :price_year a year, lets you invite friends to sing and start choir groups, and adds more storage, MP3 and stems, and unlimited private arrangements.']]],
    'site.compare.pages.choirmate' => ['faq' => [2 => [1 => 'Yes: every course and every song in the library, with a free account, and unlimited workouts, your history, and joining any song or choir group you\'re invited to. Plus, for :price_month a month or :price_year a year, lets you invite friends to sing and start choir groups, and adds more storage, MP3 and stems, and unlimited private arrangements.']]],
    'site.compare.pages.cyberbass-and-choralia' => ['faq' => [2 => [1 => 'Yes: every course and every song in the library, with a free account, and unlimited workouts, your history, and joining any song or choir group you\'re invited to. Plus, for :price_month a month or :price_year a year, lets you invite friends to sing and start choir groups, and adds more storage, MP3 and stems, and unlimited private arrangements.']]],
];
