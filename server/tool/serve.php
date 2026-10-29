<?php

declare(strict_types=1);

/**
 * The local server, sturdier than `php artisan serve`:
 *
 *   PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:8000 tool/serve.php
 *
 * It finds public/ by its absolute path on every request, so a `git rebase` that recreates the
 * folder (as landing on main does) never leaves the server pointing at a deleted directory.
 */
$public = dirname(__DIR__).'/public';
chdir($public);

$uri = urldecode((string) parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH));

// The `media` disk's local stand-in for the CDN (MEDIA_URL defaults to /media here): public,
// cookie-free files, so any origin may fetch them (the app does, with mode 'cors', to keep guides
// offline), as the Spaces bucket's CORS rule allows in production.
if (str_starts_with($uri, '/media/') && ! str_contains($uri, '..')) {
    $file = dirname(__DIR__).'/storage/app/media/'.substr($uri, strlen('/media/'));
    header('Access-Control-Allow-Origin: *');

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        header('Access-Control-Allow-Methods: GET, HEAD');
        header('Access-Control-Max-Age: 86400');
        http_response_code(204);

        return true;
    }

    if (! is_file($file)) {
        http_response_code(404);

        return true;
    }

    $types = ['m4a' => 'audio/mp4', 'mp4' => 'video/mp4', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'json' => 'application/json'];
    header('Content-Type: '.($types[strtolower(pathinfo($file, PATHINFO_EXTENSION))] ?? 'application/octet-stream'));
    header('Content-Length: '.filesize($file));
    header('Cache-Control: public, max-age=31536000, immutable');
    header('X-Content-Type-Options: nosniff');
    readfile($file);

    return true;
}

// public/'s own files (the admin's passkeys.js), served here rather than by the built-in server,
// whose document root would go stale the same way.
if ($uri !== '/' && ! str_contains($uri, '..') && is_file($public.$uri)) {
    $types = ['js' => 'text/javascript; charset=utf-8', 'css' => 'text/css; charset=utf-8', 'txt' => 'text/plain; charset=utf-8', 'ico' => 'image/x-icon', 'svg' => 'image/svg+xml', 'png' => 'image/png'];
    header('Content-Type: '.($types[pathinfo($uri, PATHINFO_EXTENSION)] ?? 'application/octet-stream'));
    header('X-Content-Type-Options: nosniff');
    readfile($public.$uri);

    return true;
}

$_SERVER['SCRIPT_FILENAME'] = $public.'/index.php';

require $public.'/index.php';
