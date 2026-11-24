<?php

declare(strict_types=1);

/*
 * The singing test (docs/singing-test-v1.md). Its scoring is code (App\Support\SingingTest\Scoring, shared with the app
 * through contract/singing-test/); here, what the server takes.
 */
return [
    // The test versions the server keeps (a new version adds its number once its items exist).
    'test_versions' => [1],
];
