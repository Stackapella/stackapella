<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Copyright terms by country (docs/geo-rights-plan.md)
|--------------------------------------------------------------------------
| Every country's term for music and its words, each with its source, read from resources/data/copyright-terms.json
| (compiled from WIPO Lex and each country's law; every entry `reviewed: false` until the lawyer has read it).
| App\Support\Rights\SongRights applies them: a country's `life` (years after the last maker's death, to the year's
| end), `exceptions`, `freeIfDiedBefore` (an extension that didn't revive what was free), `shorterTerm` (only where
| the law applies it) and the US's `publicationBased`. Withhold rather than guess.
*/

$path = resource_path('data/copyright-terms.json');
$table = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

return [
    // Territories under another country's copyright law: they take its row (France's overseas departments and
    // collectivities, the US territories, Åland, Svalbard, the Australian islands, the Caribbean Netherlands, Greenland,
    // the Canary Islands and Ceuta and Melilla).
    'inherits' => [
        'GF' => 'FR', 'GP' => 'FR', 'MQ' => 'FR', 'RE' => 'FR', 'YT' => 'FR', 'PM' => 'FR', 'BL' => 'FR', 'MF' => 'FR',
        'NC' => 'FR', 'PF' => 'FR', 'WF' => 'FR', 'TF' => 'FR', 'CP' => 'FR',
        'AS' => 'US', 'GU' => 'US', 'MP' => 'US', 'VI' => 'US', 'UM' => 'US',
        'AX' => 'FI', 'SJ' => 'NO', 'BV' => 'NO', 'CC' => 'AU', 'CX' => 'AU', 'NF' => 'AU', 'HM' => 'AU',
        'BQ' => 'NL', 'GL' => 'DK', 'IC' => 'ES', 'EA' => 'ES', 'DG' => 'GB', 'CQ' => 'GG',
    ],
    // Codes that aren't a place's own law (the EU, the UN, unknown and private-use codes): the strictest rule.
    'notCountries' => ['EU', 'EZ', 'UN', 'QO', 'XA', 'XB', 'ZZ'],

    'countries' => is_array($table) ? array_filter($table, static fn (mixed $row, string $code): bool => is_array($row) && preg_match('/^[A-Z]{2}$/', $code) === 1, ARRAY_FILTER_USE_BOTH) : [],
];
