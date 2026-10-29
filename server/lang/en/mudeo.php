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
        'paragraph_1' => 'It\'s Hillel, who made mudeo. You signed up to record yourself singing and stack the parts into a harmony. Thank you for being part of it.',
        'paragraph_2' => 'mudeo closed a few months ago. The honest reason: I could never get the videos to line up. Every phone and browser adds its own delay, and with video on top, the parts never quite sat together the way a harmony needs to. Many of you also told me you mostly wanted the sound.',
        'paragraph_3' => 'So I rebuilt it from the ground up as *Stackapella*, audio only. It measures your setup\'s delay and corrects for it, so *every part you stack lines up exactly*, and your voices sound like one choir, not an echo.',
        'paragraph_4' => 'It\'s also become much more than a recorder. It teaches you to sing harmony, from your very first note:',
        'point_1' => '*Find your voice in a minute.* Hum a note and it works out where you sing comfortably, then starts each lesson there.',
        'point_2' => '*See your pitch as you sing.* Notes glide past like a game and light up when you hit them, with a gentle "a little low, go up" when you drift.',
        'point_3' => '*Learn a part one phrase at a time.* Listen, sing along, then sing it on your own. Some parts come with real singers\' voices.',
        'point_4' => '*Stack the voices yourself.* Record the melody, then add harmonies while hearing the others, with a mixer, trimming and a one-tap polished sound.',
        'point_5' => '*Write your own song.* Hum a tune and it writes the notes and suggests the harmony parts. Or pick a chord progression, or a 12-bar blues, and sing over a backing.',
        'point_6' => '*Sing with friends.* Invite them to add their parts to your song, or share a link or a short video when it\'s done.',
        'after_0' => 'It runs in your browser on your phone or computer, with nothing to install, and it\'s *free to start*. Phone and desktop apps are on the way.',
        'invited' => 'You\'re invited in early',
        'invite' => 'It isn\'t open to everyone yet, but as a mudeo singer you can try it now. We don\'t require an account; make one any time to keep your songs across devices.',
        'button' => 'Try Stackapella',
        'card_line' => 'Works in your browser on phone or computer. Nothing to install.',
        'after_1' => 'As one of the first singers in, you\'ll shape what comes next: tell me what\'s confusing or what you\'d love it to do.',
        'after_2' => '*Your mudeo videos:* if you\'d like a copy of any videos you made on mudeo, reply to this email and I\'ll send them.',
        'after_3' => 'I\'d love to hear what you make. Just reply; I read every message.',
        'signature' => 'Hillel',
        'ps' => 'P.S.',
        'footer' => 'Sent to :email because you had a mudeo account. This is the only email we\'ll send. [link].',
        'unsubscribe_link' => 'Unsubscribe in one click',
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
