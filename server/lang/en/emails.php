<?php

declare(strict_types=1);

// The parts every email shares (resources/views/emails/layout.blade.php): the brand, the link box
// and the footer, which says who it went to and why.
return [
    'brand' => 'Stackapella',
    // The game skin (App\Support\Mail\EmailSkin): the small label over the heading.
    'game' => ['hud' => 'Player 1'],
    'paste' => 'Button not working? Paste this into your browser:',
    'sent_to' => 'Sent to :email because :because. Questions? Just reply.',
    'sent_you' => 'Sent to you because :because.',
    'because' => [
        'default' => 'of something you did on Stackapella',
        'admin' => 'you\'re one of Stackapella\'s admins',
    ],
];
