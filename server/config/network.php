<?php

declare(strict_types=1);

// The network features panel (docs/roadmap.md, "After launch"): what each feature waits for, as
// proposed. Hillel changes them in admin (admin.network.update, in sudo); his are kept as a setting.
return [

    'features' => [
        'N1' => ['title' => 'The gallery', 'flag' => 'gallery', 'thresholds' => ['harmonies' => 300, 'singers' => 100]],
        'N2' => ['title' => 'Leagues and friends\' leaderboards', 'flag' => 'leagues', 'thresholds' => ['active' => 1000, 'galleryOpen' => 1]],
        'N3' => ['title' => 'The public template library', 'flag' => 'template_library', 'thresholds' => ['templates' => 50, 'creators' => 20]],
        'N4' => ['title' => 'The coach directory', 'flag' => 'coach_directory', 'thresholds' => ['coaches' => 15, 'rated' => 10, 'active' => 500]],
        'N5' => ['title' => 'The open parts shelf', 'flag' => 'open_parts', 'thresholds' => ['galleryOpen' => 1, 'openParts' => 50]],
        'N6' => ['title' => 'The virtual choir', 'flag' => 'virtual_choir', 'thresholds' => ['voices' => 30, 'parts' => 3]],
        'N7' => ['title' => 'Song requests and votes', 'flag' => 'song_requests', 'thresholds' => ['active' => 500]],
    ],

];
