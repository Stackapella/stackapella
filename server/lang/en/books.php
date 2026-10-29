<?php

declare(strict_types=1);

// Words written into Invoice Ninja (the coach's and Stackapella's books), and the plain reasons
// a recording failed, which the coach and Hillel are shown.
return [
    'line' => [
        'review' => 'Take review: :title, for :singer',
        'lesson' => 'Lesson, :date (:minutes minutes): :title, for :singer',
        'package' => 'Monthly coaching (:month): :title, for :singer',
        'pack' => 'Exercise pack: :title, for :singer',
        'plus' => 'Stackapella Plus',
        'plus_year' => 'Stackapella Plus, :months months',
        'commission' => 'Commission on coaching sales, :month',
        'commission_sale' => ':description (:amount)',
        'commission_carried' => 'Carried over from earlier months',
        'tax' => 'Tax',
        'refund' => 'A refund',
    ],
    'fee' => 'Stackapella commission on :description',
    'fee_back' => 'Stackapella commission given back on :description',
    'vendor' => 'Stackapella',
    'reasons' => [
        'unreachable' => 'Invoice Ninja didn\'t answer.',
        'tls' => 'Invoice Ninja\'s secure connection failed (its certificate).',
        'bad_token' => 'Invoice Ninja didn\'t accept the token. Connect it again.',
        'not_allowed' => 'That address can\'t be used: it must be a public https address.',
        'redirected' => 'Invoice Ninja sent us somewhere else. Check the address.',
        'refused' => 'Invoice Ninja didn\'t make it.',
        'paused' => 'Recording is paused until Invoice Ninja is connected again.',
        'waiting' => 'Waiting for the sale to be recorded first.',
    ],
];
