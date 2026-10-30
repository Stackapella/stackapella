<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Initials the boards refuse (game mode's arcade initials)
|--------------------------------------------------------------------------
|
| Three-letter combinations nobody may show on a board: slurs, insults and the obvious. Checked
| case-insensitively by account.update; the app only shows the message, and never gets the list.
|
*/

return [
    'blocked' => [
        'ASS', 'AZZ', 'BCH', 'BJS', 'CNT', 'COC', 'COK', 'CUM', 'CUN', 'DIC', 'DIK', 'DIX', 'DMN',
        'FAG', 'FAP', 'FCK', 'FKU', 'FUC', 'FUK', 'FUQ', 'FUX', 'GAS', 'GOD', 'HOE', 'HOR', 'JEW',
        'JIZ', 'KKK', 'KUM', 'KYS', 'NAZ', 'NGA', 'NGR', 'NIG', 'NIP', 'PIS', 'POO', 'PUS', 'PUZ',
        'RAP', 'SEX', 'SHT', 'SHI', 'SLT', 'SOB', 'STD', 'SUK', 'TIT', 'TWT', 'VAG', 'WAN', 'WTF',
        'XXX', 'HIV', 'IRA', 'KMS', 'FKN', 'FKY',
    ],
];
