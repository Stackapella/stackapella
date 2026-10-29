<?php

declare(strict_types=1);

/*
 * Each hot read's cost on realistic data (docs/performance.md, "Local performance pass"): the
 * request run through the app in this process, several times, with its time, its queries (count,
 * total time, the slowest) and its size. Against the scratch database only (its name ends in
 * "_perf"), seeded by tool/load/seed.php.
 *
 *   export DB_DATABASE=stackapella_perf
 *   php tool/load/profile.php <an access token from seed.php> [runs]
 */

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (! str_ends_with(DB::connection()->getDatabaseName(), '_perf')) {
    fwrite(STDERR, "profile: not a scratch database (its name must end in _perf).\n");
    exit(1);
}

$token = (string) ($argv[1] ?? '');
$runs = max(1, (int) ($argv[2] ?? 5));
$reads = [
    ['auth.config', '/api/auth/config', false],
    ['templates.catalogue', '/api/templates', false],
    ['billing.prices', '/api/billing/prices', false],
    ['explore.list', '/api/explore', false],
    ['ideas.list', '/api/ideas', false],
    ['choirs.show', '/api/choirs/amazing-grace', false],
    ['choirs.voices.list', '/api/choirs/amazing-grace/voices', false],
    ['account.show', '/api/account', true],
    ['billing.show', '/api/billing', true],
    ['account.learning.show', '/api/account/learning', true],
    ['sync.pull (since 0)', '/api/sync?since=0', true],
    ['templates.catalogue, signed in', '/api/templates', true],
    ['usage.show', '/api/usage', true],
    ['bin.list', '/api/bin', true],
    ['groups.list', '/api/groups', true],
];

$queries = [];
DB::listen(static function (QueryExecuted $query) use (&$queries): void {
    $queries[] = [$query->time, $query->sql];
});

printf("%-32s %6s %8s %8s %8s %12s %9s  %s\n", 'read', 'status', 'ms', 'queries', 'db ms', 'cache q/ms', 'bytes', 'slowest query');
foreach ($reads as [$name, $path, $signedIn]) {
    if ($signedIn && $token === '') {
        continue;
    }

    $times = [];
    foreach (range(0, $runs) as $run) {
        $queries = [];
        $request = Request::create($path, 'GET', server: ['HTTP_ACCEPT' => 'application/json', 'REMOTE_ADDR' => '10.9.'.random_int(0, 250).'.'.random_int(1, 250)]);
        if ($signedIn) {
            $request->headers->set('Authorization', 'Bearer '.$token);
        }
        $start = hrtime(true);
        $response = $kernel->handle($request);
        $ms = (hrtime(true) - $start) / 1e6;
        $kernel->terminate($request, $response);
        $app['auth']->forgetGuards();

        // The first run warms what's loaded once per process.
        if ($run > 0) {
            $times[] = $ms;
        }
    }

    sort($times);
    usort($queries, static fn (array $a, array $b): int => $b[0] <=> $a[0]);
    // The database cache's own queries (the rate limiters' counters): what Valkey or Redis would take.
    $cache = array_values(array_filter($queries, static fn (array $q): bool => preg_match('/\\b(from|into|update) `cache(_locks)?`/', $q[1]) === 1));
    printf("%-32s %6d %8.1f %8d %8.1f %5d/%5.1f %9d  %s\n", $name, $response->getStatusCode(), $times[intdiv(count($times), 2)], count($queries), array_sum(array_column($queries, 0)),
        count($cache), array_sum(array_column($cache, 0)),
        strlen((string) $response->getContent()), isset($queries[0]) ? sprintf('%.1f ms: %s', $queries[0][0], mb_substr(preg_replace('/\s+/', ' ', $queries[0][1]) ?? '', 0, 110)) : '');
}
