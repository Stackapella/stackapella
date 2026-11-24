<?php

declare(strict_types=1);

/*
 * Choir seats (docs/choir-seats-plan.md; Hillel, 2026-11-18): what each place that promised a choir's members join free
 * says while the choir_seats switch is on, laid over the page's words (and over teams.php's) by App\Support\Site\Teams,
 * keyed by the full key it changes. Every member of a choir group needs a seat, bought by its director for the choir; a
 * seat is Plus, so a singer with their own Plus has one; friends invited to a song still join free; learning alone
 * stays free. Only the changed words are here.
 */
return [
    // The landing's choir section, under the Plus badge.
    'landing.choir.plus' => 'Every singer in a choir group has a seat, bought for the choir by its director. Learning alone stays free.',
    // The plan cards (landing and /pricing).
    'pages.landing.plans_free_list' => [3 => 'Join any song you\'re invited to'],
    'pages.landing.plans_choirs_link' => 'a seat for each singer, and learning tracks for your choir',
    // The landing's questions.
    // /pricing.
    'pages.pricing.rows' => [
        'groups' => ['Choir groups (18 and over)', 'A seat from your director', 'A seat in any group'],
    ],
    'pages.pricing.faq' => [2 => [1 => 'Each singer in a choir group needs a seat. The director buys them for the choir, from 10 seats; a singer with Plus already has one. Learning alone stays free. We also make learning tracks of a choir\'s own songs, and your first song is free. See the page for choir directors.']],
    // Help.
    'pages.help.pages.plus-and-billing.sections' => [0 => [1 => [2 => 'Record every part, polish the mix, export as WAV, and share links and videos. You can join any song you\'re invited to; inviting friends to sing on your songs takes Plus, and everyone you invite joins free. Singing in a choir group takes a seat: your own Plus, or one your director buys for the choir.']]],
    'pages.help.pages.groups.sections' => [0 => [1 => [0 => 'A choir director starts a group, sends the singers its link, shares songs, and says who sings which part. Every singer needs a seat: the director buys them for the choir, and a singer with Plus already has one.']]],
    // The choirs feature page.
    'site.features.pages.choirs' => [
        'description' => 'Directors share songs, give each singer a part, and see who has started and recorded it. Members learn at home with guide voices, each with a seat the director buys. 18 and over.',
        'sections' => [2 => ['body' => 'Every singer in a choir group has a seat, which the director buys for the choir, from 10 seats. A singer with Plus already has one, and learning alone stays free.']],
    ],
    // The landing's together section.
    'site.teams.items' => [2 => [1 => 'Start one and buy a seat for each singer: share songs with your choir, give each singer a part, and see who has started and who has recorded it. You never see their recordings or contact details. For singers 18 and over.']],
    // The carols page.
    'site.carols.choirs_body' => 'Start a choir group with a seat for each singer: one link brings them in, you give each their part, and you see who has started and recorded it before rehearsal.',
    // For choir directors.
    'site.for.pages.choir-directors' => ['faq' => [0 => [1 => 'Your first song\'s learning tracks are free. Each singer in your group needs a seat, which you buy for the choir from 10 seats; a singer with Plus already has one. When a singer leaves, move their seat to someone new, up to 3 times in any 30 days.']]],
    // The compare pages' "Is Stackapella free?" (each page's third question), the same answer on every page.
    'site.compare.pages.howli' => ['faq' => [2 => [1 => 'Yes: every course and every song in the library, with a free account, and unlimited workouts, your history, and joining any song you\'re invited to. Plus, for :price_month a month or :price_year a year, lets you invite friends to sing, is your seat in any choir group, and adds more storage, MP3 and stems, and unlimited private arrangements.']]],
    'site.compare.pages.harmony-helper' => ['faq' => [2 => [1 => 'Yes: every course and every song in the library, with a free account, and unlimited workouts, your history, and joining any song you\'re invited to. Plus, for :price_month a month or :price_year a year, lets you invite friends to sing, is your seat in any choir group, and adds more storage, MP3 and stems, and unlimited private arrangements.']]],
    'site.compare.pages.sing-harmonies' => ['faq' => [2 => [1 => 'Yes: every course and every song in the library, with a free account, and unlimited workouts, your history, and joining any song you\'re invited to. Plus, for :price_month a month or :price_year a year, lets you invite friends to sing, is your seat in any choir group, and adds more storage, MP3 and stems, and unlimited private arrangements.']]],
    'site.compare.pages.acapella' => ['faq' => [2 => [1 => 'Yes: every course and every song in the library, with a free account, and unlimited workouts, your history, and joining any song you\'re invited to. Plus, for :price_month a month or :price_year a year, lets you invite friends to sing, is your seat in any choir group, and adds more storage, MP3 and stems, and unlimited private arrangements.']]],
    'site.compare.pages.yousician' => ['faq' => [2 => [1 => 'Yes: every course and every song in the library, with a free account, and unlimited workouts, your history, and joining any song you\'re invited to. Plus, for :price_month a month or :price_year a year, lets you invite friends to sing, is your seat in any choir group, and adds more storage, MP3 and stems, and unlimited private arrangements.']]],
    'site.compare.pages.smule' => ['faq' => [2 => [1 => 'Yes: every course and every song in the library, with a free account, and unlimited workouts, your history, and joining any song you\'re invited to. Plus, for :price_month a month or :price_year a year, lets you invite friends to sing, is your seat in any choir group, and adds more storage, MP3 and stems, and unlimited private arrangements.']]],
    'site.compare.pages.choirmate' => ['faq' => [2 => [1 => 'Yes: every course and every song in the library, with a free account, and unlimited workouts, your history, and joining any song you\'re invited to. Plus, for :price_month a month or :price_year a year, lets you invite friends to sing, is your seat in any choir group, and adds more storage, MP3 and stems, and unlimited private arrangements.']]],
    'site.compare.pages.cyberbass-and-choralia' => ['faq' => [2 => [1 => 'Yes: every course and every song in the library, with a free account, and unlimited workouts, your history, and joining any song you\'re invited to. Plus, for :price_month a month or :price_year a year, lets you invite friends to sing, is your seat in any choir group, and adds more storage, MP3 and stems, and unlimited private arrangements.']]],
    // For choir directors (the new page).
    'site.for_directors.note' => 'Each singer needs a seat, which you buy for the choir from 10 seats. A singer with Plus already has one.',
    'site.for_directors.steps' => [0 => [1 => 'Starting a group takes Plus, and each singer needs a seat, bought for the choir from 10 seats. Groups are for singers 18 and over.']],
    'site.for_directors.faq' => [0 => [1 => 'Each singer needs a seat, which you buy for the choir from 10 seats; a singer with Plus already has one. Your first song\'s learning tracks are free.']],
    // /pricing's choir callout.
    'pages.pricing.choir_callout' => 'Leading a choir? Each singer needs a seat, which you buy for the choir from 10 seats, and your first song\'s learning tracks are on us.',
];
