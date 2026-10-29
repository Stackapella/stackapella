<?php

declare(strict_types=1);

/**
 * Serves the web app locally, as nginx does in production:
 *
 *   php -S localhost:8081 tool/serve.php                               # the mock API (?api=mock)
 *   API=http://127.0.0.1:8000 php -S localhost:8081 tool/serve.php     # the real Laravel server
 *
 * - Files come from /web as they are; any other path is the app (index.html), so /p/<id> and
 *   /admin reloads work.
 * - With API set to a server, /api and the server's own pages (admin sign-in and second factor,
 *   /auth, /dev, /assets, /up, and the local stand-ins for the media buckets, /media and
 *   /private-media) are forwarded to it on this same origin, so cookies and CSRF
 *   behave as in production (docs/parallel-agents-plan.md, "The integration check").
 * - /contract/* is served from the repo for the mock, which reads the contract's examples.
 * - Only what production serves is served: the folders and files in WebAppController::SERVED
 *   (read from it, so the two can't drift), plus tests/ for the test pages. A file anywhere else
 *   is a 404 here as it is in production, so a new top-level folder fails locally first.
 * - POST /test-result writes a test run's report to tests/last-result.json.
 */
$root = realpath(__DIR__.'/..');
$repo = dirname($root);
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
$api = getenv('API') ?: 'mock';

if ($path === '/test-result' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    file_put_contents($root.'/tests/last-result.json', file_get_contents('php://input'));
    http_response_code(204);

    return true;
}

$serverPaths = '#^/(api(/|$)|admin/(sign-in|sign-out|second-factor)(/|$)|auth/|dev(/|$)|assets/|media/|private-media/|up$)#';
if (preg_match($serverPaths, $path) === 1) {
    if ($api === 'mock' && $path === '/up') {
        // The mock's version (contract/api.md, "The version"): for the admin's badge and "A new
        // version is ready". tests/flows/deploy.py writes tool/mock-build.txt to play a deploy.
        $build = is_file(__DIR__.'/mock-build.txt') ? trim((string) file_get_contents(__DIR__.'/mock-build.txt')) : 'mock0000000000000000000000000000000000000';
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        echo json_encode(['status' => 'ok', 'build' => $build, 'builtAt' => null]);
        return true;
    }
    if ($api === 'mock' && preg_match('#^/admin/(sign-in|second-factor)$#', $path) === 1) {
        // A stand-in for the server's own sign-in and second-factor pages, which go back where
        // the person was going. (A developer page, so its words aren't translated.)
        $intended = (string) ($_GET['intended'] ?? '/admin');
        $back = str_starts_with($intended, '/admin') || str_starts_with($intended, '/styleguide') ? $intended : '/admin';
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><meta charset="utf-8"><title>Mock '.htmlspecialchars($path).'</title><body style="font:16px system-ui;padding:2rem;background:#17120f;color:#f4eee6">'
            .'<p>The server\'s '.htmlspecialchars($path).' page goes here (the mock API has no server).</p>'
            .'<p><a style="color:#4ccfb0" href="'.htmlspecialchars($back).'">Continue as the demo admin</a></p></body>';

        return true;
    }
    if ($api === 'mock' && $path === '/dev/moving') {
        // A stand-in for the server's "Stackapella has moved" page (public.moving), for
        // tests/flows/moving.py: the same markup and script, `to` the new address.
        $to = (string) ($_GET['to'] ?? '/');
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Stackapella</title>'
            .'<script src="/js/theme-boot.js"></script><link rel="stylesheet" href="/css/app.css"></head>'
            .'<body class="min-h-dvh bg-ink font-sans text-cream antialiased"><main class="mx-auto flex min-h-dvh max-w-xl flex-col justify-center gap-6 px-6 py-16">'
            .'<section class="card space-y-4 p-6 md:p-8" data-moving data-app-url="'.htmlspecialchars($to).'" aria-live="polite"></section></main>'
            .'<script type="module" src="/js/public/moving.js"></script></body></html>';

        return true;
    }
    if ($api === 'mock') {
        // The app answers these from the mock (js/api-mock.js); a request here is a bug.
        header('Content-Type: application/json');
        http_response_code(404);
        echo json_encode(['message' => 'The mock API runs in the browser (?api=mock). Start with API=http://127.0.0.1:8000 for the real one.', 'code' => 'not_found']);

        return true;
    }
    forward($api, $_SERVER['REQUEST_URI']);

    return true;
}

// /contract/ serves only the contract folder: never anything else in the repo (.env, .git).
$contract = str_starts_with($path, '/contract/');

// Production serves only these (server/app/Http/Controllers/WebAppController.php, .do/nginx.conf).
$relative = ltrim($path, '/');
$served = [...servedByProduction($repo), 'tests/'];
$allowed = $contract || $relative === '' || $relative === 'index.html'
    || array_filter($served, fn (string $entry): bool => str_ends_with($entry, '/') ? str_starts_with($relative, $entry) : $relative === $entry) !== [];
if (! $allowed && pathinfo($path, PATHINFO_EXTENSION) !== '') {
    header('Content-Type: application/json');
    http_response_code(404);
    echo json_encode(['message' => "Production doesn't serve {$path}: add its folder to WebAppController::SERVED and .do/nginx.conf.", 'code' => 'not_found']);

    return true;
}
$base = $contract ? $repo.'/contract' : $root;
$file = realpath($base.($contract ? substr($path, strlen('/contract')) : $path));
if ($file !== false && is_dir($file) && is_file($file.'/index.html')) {
    $file .= '/index.html';
}
$inside = $file !== false && str_starts_with($file, $base.DIRECTORY_SEPARATOR) && is_file($file)
    && ! str_starts_with($file, $root.'/tool') && ! str_starts_with($file, $root.'/bin');
if (! $inside) {
    $file = $root.'/index.html';
}

$types = [
    'html' => 'text/html; charset=utf-8',
    'js' => 'text/javascript; charset=utf-8',
    'css' => 'text/css; charset=utf-8',
    'json' => 'application/json',
    'md' => 'text/markdown; charset=utf-8',
    'webmanifest' => 'application/manifest+json',
    'svg' => 'image/svg+xml',
    'png' => 'image/png',
];
header('Content-Type: '.($types[pathinfo($file, PATHINFO_EXTENSION)] ?? 'application/octet-stream'));
// As production's nginx (.do/nginx.conf): every file revalidates with its ETag, a cheap 304 when
// unchanged, so a deploy (or an edit) shows on the next load, never mixed with older modules. A
// max-age would let the browser's memory cache answer a module before the service worker sees it
// (tests/flows/deploy.py).
header('Cache-Control: no-cache');
header('ETag: "'.md5_file($file).'"');
if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === '"'.md5_file($file).'"') {
    http_response_code(304);
    return true;
}
readfile($file);

return true;

/** The folders (ending in /) and files production serves from /web, as WebAppController lists them. */
function servedByProduction(string $repo): array
{
    $source = @file_get_contents($repo.'/server/app/Http/Controllers/WebAppController.php');
    if ($source === false || preg_match('/SERVED\s*=\s*\[(.*?)\];/s', $source, $list) !== 1) {
        error_log('serve.php: WebAppController::SERVED not found; serving every file');

        return [''];
    }
    preg_match_all("/'([^']+)'/", $list[1], $entries);

    return $entries[1];
}

/** Passes the request to the API server and its answer back, keeping this origin's Host. */
function forward(string $api, string $uri): void
{
    $keep = ['accept', 'content-type', 'cookie', 'x-xsrf-token', 'x-app-version', 'x-requested-with', 'origin', 'referer', 'user-agent'];
    $headers = ['Host: '.($_SERVER['HTTP_HOST'] ?? 'localhost'), 'X-Forwarded-For: '.($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1')];
    foreach (getallheaders() as $name => $value) {
        if (in_array(strtolower($name), $keep, true)) {
            $headers[] = "{$name}: {$value}";
        }
    }
    $responseHeaders = [];
    $curl = curl_init(rtrim($api, '/').$uri);
    curl_setopt_array($curl, [
        CURLOPT_CUSTOMREQUEST => $_SERVER['REQUEST_METHOD'],
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HEADERFUNCTION => function ($curl, string $line) use (&$responseHeaders): int {
            $responseHeaders[] = $line;

            return strlen($line);
        },
    ]);
    if (! in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, file_get_contents('php://input'));
    }
    $body = curl_exec($curl);
    if ($body === false) {
        header('Content-Type: application/json');
        http_response_code(502);
        echo json_encode(['message' => 'The API server at '.$api.' did not answer: '.curl_error($curl), 'code' => 'server_error']);

        return;
    }
    http_response_code(curl_getinfo($curl, CURLINFO_RESPONSE_CODE));
    $passOn = ['content-type', 'set-cookie', 'location', 'retry-after', 'cache-control', 'content-disposition', 'www-authenticate'];
    foreach ($responseHeaders as $line) {
        $name = strtolower(trim(strstr($line, ':', true) ?: ''));
        if (in_array($name, $passOn, true)) {
            // A redirect to the API server's own address comes back to this origin instead.
            header(trim(str_replace(rtrim($api, '/'), '', $line)), false);
        }
    }
    echo $body;
}
