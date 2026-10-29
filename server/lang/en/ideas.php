<?php

declare(strict_types=1);

// Ideas (docs/your-tune-plan.md, step 3): the writer's email about the voices on their tune, and
// its unsubscribe page. :name, :part, :title and :count are filled in.
return [
    'mail' => [
        'subject_one' => ':name sang the :part of your tune',
        'subject_one_played' => ':name played the :part of your tune',
        'subject_many' => ':count new voices on your tune',
        'subject_complete' => 'Every part of your tune has a voice',
        'title' => 'New voices on ":title"',
        'voice' => ':name sang the :part.',
        'voice_played' => ':name played the :part.',
        'complete' => 'Every part of ":title" has a voice now: hear it all together.',
        'button' => 'Hear your tune',
        'note_lead' => 'One a day at most',
        'note' => 'for each tune you share. Turn them off in Settings, or in one click: :unsubscribe',
        'because' => 'you shared a tune on Stackapella\'s Ideas shelf',
        'a_part' => 'a part',
    ],
    'unsubscribe' => [
        'title' => 'Emails about your ideas',
        'ask' => 'Stop emails telling you who sang your tunes? The bell in the app still tells you.',
        'button' => 'Turn them off',
        'done_title' => 'They\'re off',
        'done' => 'We won\'t email you about your ideas. Turn them back on in Settings.',
    ],
];
