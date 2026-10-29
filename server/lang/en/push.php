<?php

declare(strict_types=1);

// Pushes to the native apps (App\Support\Push\AppPushes): what the bell says, as a notification.
return [
    'like' => ['title' => 'New like', 'body' => ':name liked “:title”.'],
    'like_others' => ['body' => ':name and :count other liked “:title”.|:name and :count others liked “:title”.'],
    'comment' => ['title' => 'New comment', 'body' => ':name commented on “:title”.'],
    'follow' => ['title' => 'New follower', 'body' => ':name followed you.'],
    'consent_request' => ['title' => 'May it be shared?', 'body' => ':name asks to share “:title”, which you sing on.'],
    'consent_answer' => ['title' => 'Sharing answered', 'body' => ':name answered about sharing “:title”.'],
    'featured' => ['title' => 'Featured', 'body' => '“:title” is featured in the gallery.'],
    'idea_voice' => ['title' => 'A new voice', 'body' => ':name sang a part on your idea “:title”.'],
    'idea_complete' => ['title' => 'Every part is sung', 'body' => 'Every part of your idea “:title” is sung.'],
    'fill' => ['title' => 'Your open part was sung', 'body' => ':name sang the open part on “:title”.'],
    'request_ready' => ['title' => 'Your song is ready', 'body' => 'The song you asked for, “:title”, is ready to sing.'],
    'board_beaten' => ['title' => 'Your score was beaten', 'body' => ':name beat your score on :part in “:title” (:score).'],
];
