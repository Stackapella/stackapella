<?php

declare(strict_types=1);

/*
 * Outreach drafts (docs/outreach-plan.md): a start for each angle that Hillel edits before sending,
 * plain text. Short, as Hillel asked (2026-11-05: "just asking if they're willing to take a look at
 * the app"): Hillel's own words (2026-11-07), a few features that fit the group, the main page (:link) and his name.
 */
return [
    'angles' => [
        'school' => [
            'subject' => 'Would you take a look at my singing app?',
            'body' => "Hi,\n\nMy name is Hillel. I'm a singer and programmer and have been working on an app to help people (including myself) become better singers.\n\nFor students, there are free courses on pitch, ear training, sight-singing and harmony, a guide voice for each part, and feedback on how close they are to each note as they sing.\n\nThe courses are all free, if you have the time to take a look I'd very much appreciate any feedback you have.\n\n:link\n\nCheers,\nHillel",
        ],
        'choir_groups' => [
            'subject' => 'Would you take a look at my singing app?',
            'body' => "Hi,\n\nMy name is Hillel. I'm a singer and programmer and have been working on an app to help people (including myself) become better singers.\n\nFor a choir, each singer can learn their own part at home with a guide voice singing it alongside them and see how close they are to each note, and a director can share songs with the choir, give each singer a part and see who has practised.\n\nIf you send me a song your choir is rehearsing, I'll make part-by-part learning tracks for your singers, free (it needs to be public domain or your own arrangement).\n\nThe courses are all free, if you have the time to take a look I'd very much appreciate any feedback you have.\n\n:link\n\nCheers,\nHillel",
        ],
        'course' => [
            'subject' => 'Would you take a look at my singing app?',
            'body' => "Hi,\n\nMy name is Hillel. I'm a singer and programmer and have been working on an app to help people (including myself) become better singers.\n\nEach singer can learn their part with a guide voice and see how close they are to each note, then record it over the other parts to hear the whole arrangement.\n\nThe courses are all free, if you have the time to take a look I'd very much appreciate any feedback you have.\n\n:link\n\nCheers,\nHillel",
        ],
        'practice_tool' => [
            'subject' => 'Would you take a look at my singing app?',
            'body' => "Hi,\n\nMy name is Hillel. I'm a singer and programmer and have been working on an app to help people (including myself) become better singers.\n\nEach singer can learn their part with a guide voice and see how close they are to each note, then record it over the other parts to hear the whole arrangement.\n\nThe courses are all free, if you have the time to take a look I'd very much appreciate any feedback you have.\n\n:link\n\nCheers,\nHillel",
        ],
    ],

    'import' => [
        'suppressed' => 'This address is on the do-not-contact list.',
        'in_progress' => 'Already being written to: left as it is.',
        'unverified' => 'Contact not checked on the page yet.',
        'bad_contact' => 'A contact is an email address or a contact form\'s web address.',
    ],

    'follow_up' => [
        'subject' => 'Re: :subject',
        'body' => "Hi again, just checking this didn't get buried. No worries if it's not for you.\n\n:link\n\nHillel",
        // An angle's own follow-up, where it has an offer to add (Hillel, 2026-11-08: the learning tracks, for choirs only).
        'angles' => [
            'choir_groups' => "Hi again, just checking this didn't get buried. One more thought: if you send me a song your choir is rehearsing, I'll make part-by-part learning tracks for your singers, free (it needs to be public domain or your own arrangement). No worries if it's not for you.\n\n:link\n\nHillel",
        ],
    ],
];
