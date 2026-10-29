<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Plans and limits
|--------------------------------------------------------------------------
|
| docs/backend-plan.md C6 and docs/pricing-plan.md: the free plan's projects and storage, and
| Plus's. Storage is proposed at 1 GB and 50 GB (pricing question 4), to be confirmed from real
| FLAC sizes. A null limit is unlimited. The bin never counts.
|
*/

return [

    'plans' => [
        // `singers`: friends on one of the owner's projects; `templates`: private or unlisted
        // templates kept on the server (docs/pricing-plan.md).
        'free' => [
            'projects' => 3,
            'storage_bytes' => 1024 ** 3,
            'singers' => 2,
            'templates' => 1,
        ],
        'plus' => [
            'projects' => null,
            'storage_bytes' => 50 * 1024 ** 3,
            'singers' => 20,
            'templates' => null,
        ],
    ],

    // Choir groups (contract/api.md, "Choir groups"): members a group, groups a director.
    'group_members' => 60,
    // Who may direct or join one, until Hillel decides how minors take part (Needs Hillel #29):
    // 18, asked on joining; 13 would open groups to every account.
    'group_min_age' => 18,
    'groups_per_director' => 5,

    // How long a project invite link works.
    'invite_days' => 14,

    // The longest take, in seconds (docs/backend-plan.md C1).
    'take_max_seconds' => 600,

];
