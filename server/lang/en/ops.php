<?php

declare(strict_types=1);

// What the admins are emailed about how Stackapella is running (M10/L): readiness changes, alerts
// and the daily digest.
return [
    'readiness' => [
        'subject' => 'Stackapella: :count readiness checks changed',
        'title' => 'Readiness checks changed',
        'line' => ':label went from :from to :to: :message',
        'fix' => 'Fix: :fix',
        'new' => 'new',
    ],
    // A song's new version (CarryVoicesOver): what became of each part's earlier recorded voices.
    'voices_carried' => [
        'subject' => 'Stackapella: :song is now version :version, and its recorded voices',
        'title' => 'Recorded voices after a new version',
        'intro' => ':song is now version :version. Each part that had a recorded voice before:',
        'carried' => ':part: carried over (it sings the same notes, key and tempo).',
        'changed' => ':part: needs re-recording (its notes, key or tempo changed). Learners hear the practice voice until then.',
        'choose' => 'The voices: :url',
    ],
    // A song's part lost its recorded voice (ManageGuides::remove): Hillel chooses another.
    'voice_lost' => [
        'subject' => 'Stackapella: :song lost its :part voice',
        'title' => 'A part lost its voice',
        'what' => ':part in :song: a recorded voice was taken off.',
        'now_other' => 'Learners now hear another recorded voice for it.',
        'now_synth' => 'Learners now hear the practice voice: no other recording is left for this part.',
        'why' => [
            'not_public' => 'Why: the song it came from stopped being public (made private, featuring and the gallery both off, or a singer withdrew their yes).',
            'hidden' => 'Why: the song it came from was hidden.',
            'deleted' => 'Why: the song it came from was deleted, or its account was.',
            'removed' => 'Why: it was removed in admin.',
            'forgotten' => 'Why: its beta singer asked to be forgotten.',
        ],
        'choose' => 'Choose a replacement: :url',
    ],
    'alerts' => [
        'subject' => 'Stackapella: :count alerts',
        'title' => 'Over the line',
        'where' => 'See :url',
        'sign_ups' => ':value sign-ups in the last hour (the line is :limit): an attack, or a mention somewhere.',
        'reports' => ':value reports in the last hour (the line is :limit).',
        'videos' => ':value share videos waiting to be made (the line is :limit).',
        'failed_payments' => ':value failed payments in the last hour (the line is :limit): card testing?',
        'storage' => ':value GB of new takes in a day (the line is :limit GB).',
        'limited' => ':value requests refused by the limits in the last hour (the line is :limit).',
        'suppressed' => ':value addresses suppressed in a day (bounces or complaints; the line is :limit).',
    ],
    'network' => [
        'changed' => 'The network panel: :title is now :status (:numbers).',
        'number' => ':name :value of :threshold',
        'status' => [
            'collecting' => 'collecting',
            'start_building' => 'ready to start building (half its numbers)',
            'ready_to_open' => 'ready to open',
            'open' => 'open',
        ],
    ],
    'digest' => [
        'subject' => 'Stackapella digest for :date',
        'title' => 'Yesterday, and what\'s waiting',
        'yesterday' => 'Yesterday: :signups sign-ups, :plus started Plus, :shared harmonies shared.',
        'waiting' => [
            'support' => ':count support messages are past their answer time.',
            'appeals' => ':count people asked for a review.',
            'reports' => ':count reports to look at.',
            'applications' => ':count coach applications to decide.',
            'packs' => ':count exercise packs to review.',
            'coaching_reports' => ':count coaching problems reported.',
            'submissions' => ':count beta takes to review.',
            'feedback' => ':count beta feedback messages unread.',
            'posts' => ':count posts waiting for your approval.',
            'boards' => ':count leaderboard scores to check (perfect or surprising, held off the worldwide board for up to 48 hours).',
        ],
        'check' => ':status :label: :message',
        'trouble' => [
            'failed_jobs' => ':count background jobs failed.',
            'reconnect' => ':count Event Schedule connections need reconnecting.',
            'books' => ':count Invoice Ninja recordings not recorded.',
        ],
        'all_clear' => 'Nothing is waiting, and everything is working.',
    ],
];
