<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Handles (docs/trust-and-safety-plan.md, "Handles")
|--------------------------------------------------------------------------
|
| Handles that would let someone pass as Stackapella or its people, also through look-alike letters
| ("stackape11a", "adm1n") and accents:
| - `protected`: refused anywhere in a handle ("stackapella_official", "hillel2");
| - `reserved`: refused as the whole handle, or as one of its words between underscores
|   ("support", "help_desk"), so ordinary names that merely contain them still work ("steam").
| Coaches' and partners' names join `protected` as they arrive.
|
*/

return [

    'protected' => [
        'stackapella', 'hillel', 'administrator', 'moderator', 'eventschedule', 'invoiceninja',
    ],

    'reserved' => [
        'admin', 'support', 'official', 'mod', 'staff', 'team', 'help', 'coren', 'root', 'system',
        'security', 'billing', 'payments', 'beta', 'api', 'www', 'mail', 'email', 'noreply', 'abuse',
        'legal', 'privacy', 'terms', 'coach', 'coaches', 'null', 'undefined', 'anonymous', 'me',
    ],

];
