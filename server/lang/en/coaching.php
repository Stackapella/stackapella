<?php

declare(strict_types=1);

// Coaching (contract/api.md, "Coaching, without the directory").
return [
    'off_platform_flag' => 'A message asked to pay outside the app (flagged automatically).',
    'dispute_flag' => 'The singer\'s bank disputed this payment (a chargeback, flagged automatically).',
    'deleted_account' => 'A deleted account',
    'page' => [
        'title' => ':name, voice coach on Stackapella',
    ],
    // Reasons Stackapella gives Event Schedule when it cancels a lesson there (ES may show them).
    'lesson_cancelled_in_app' => 'Cancelled in Stackapella by the :by.',
    'lesson_not_approved' => 'Not approved in time, so it was cancelled and refunded.',
    'lesson_not_booked' => 'Refunded in Stackapella before it could be booked.',
    'checkout' => [
        'review' => 'A take review from :coach',
        'package' => 'Monthly coaching with :coach',
        'pack' => ':title, from :coach',
    ],
];
