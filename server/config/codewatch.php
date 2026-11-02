<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Code watch (docs/code-watch-plan.md)
|--------------------------------------------------------------------------
|
| Knowing when someone copies the app, without tracking any person. The marker strings live in the
| environment, never in the repository: the code holds them, but only this list says which they
| are. Our own GitHub owners are left out of the search.
|
*/

$list = static fn (mixed $value): array => array_values(array_filter(array_map('trim', explode(',', is_string($value) ? $value : ''))));
$markers = static fn (mixed $value): array => array_values(array_filter(array_map('trim', explode('|', is_string($value) ? $value : ''))));

return [

    // The strings to search GitHub's code for, separated by "|" (a marker may hold a comma).
    'markers' => $markers(env('CODE_WATCH_MARKERS')),

    // A GitHub token that may use code search (no scopes needed for public code).
    'github_token' => env('CODE_WATCH_GITHUB_TOKEN'),

    // Repositories under these owners are ours.
    'own_owners' => array_map('strtolower', $list(env('CODE_WATCH_OWN_OWNERS') ?: 'hillelcoren')),

    // Another site calling our API this many times in a day shows in Admin > Code watch.
    'domain_threshold' => 20,

];
