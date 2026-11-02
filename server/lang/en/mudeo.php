<?php

declare(strict_types=1);

// The one email to mudeo's users (docs/mudeo-transition-plan.md, "The email"), and its
// unsubscribe page. Edit the words here: :first_name, :link and :unsubscribe are filled in per
// person; a "*" around a phrase makes it bold in the HTML version. To add a paragraph, add its key
// to MudeoInvite::PARAGRAPHS (or POINTS, BEFORE_INVITE, AFTER).
return [
    'mail' => [
        'subject' => 'mudeo is now Stackapella, and it finally does what mudeo couldn\'t',
        'from_name' => 'Hillel from Stackapella',
        'hi' => 'Hi :first_name,',
        'hi_plain' => 'Hi,',
        'paragraph_1' => 'It\'s Hillel, who made mudeo. Thank you for being part of it.',
        'paragraph_2' => 'mudeo closed a few months ago because I could never get the videos to line up: every phone and browser adds its own delay, so the parts never quite sat together. So I rebuilt it as *Stackapella*, audio only. It measures your setup\'s delay and corrects for it, so *every part you stack lines up exactly*.',
        'paragraph_3' => 'It also teaches you to sing harmony:',
        'point_1' => '*Learn a part* with a guide voice, and watch your notes light up as you hit them.',
        'point_2' => '*Stack every voice yourself*, hearing the others while you record.',
        'point_3' => '*Write your own song* and get harmony parts suggested.',
        'point_4' => '*Sing with friends* by inviting them to add their parts.',
        'after_0' => 'It\'s live now and *free to start*, in your browser with nothing to install. It\'s also *open source*: the code is on GitHub at github.com/stackapella/stackapella.',
        'button' => 'Try Stackapella',
        'after_1' => 'I\'d love to hear what you make, or what you\'d like it to do. Just reply; I read every message.',
        'after_2' => 'If you\'d like a copy of your mudeo videos, reply and I\'ll send them.',
        'signature' => 'Hillel',
        'ps' => 'P.S.',
        'footer' => 'Sent to :email because you had a mudeo account. This is the only email we\'ll send. [link].',
        'unsubscribe_link' => 'Unsubscribe in one click',
        // The open-source line's link in the HTML version.
        'github' => 'github.com/stackapella/stackapella',
        'github_url' => 'https://github.com/stackapella/stackapella',
    ],
    'unsubscribe' => [
        'title' => 'Unsubscribe',
        'ask' => 'Stop email to :email? It was the only one, and we won\'t write again either way.',
        'button' => 'Unsubscribe',
        'done_title' => 'You\'re unsubscribed',
        'done' => 'We won\'t email :email again.',
        'test' => 'This was a test email, so there\'s nothing to unsubscribe.',
    ],
];
