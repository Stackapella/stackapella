<?php

declare(strict_types=1);

/*
 * Deliberate repeated queries, by route name, each with the reason (Tests\Support\QueryGuard).
 *
 * 'route.name' => ['reason' => 'why it repeats', 'queries' => ['the normalised SQL']],
 */

return [
    'harmonies.update' => [
        'reason' => 'Featuring a fill (N5) reads the fill\'s owner to check it is public, and the answer reads it again for featuredFill: a fixed number of owner reads for the request, not one per row.',
        'queries' => ['select * from `users` where `users`.`id` in (?...)'],
    ],
];
