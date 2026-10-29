<?php

declare(strict_types=1);

namespace App\Support\Admin;

/**
 * Where to go after signing in to the admin: a path inside /admin on this site, never anywhere
 * else (an open redirect would make our sign-in page a phishing tool).
 */
final class IntendedUrl
{
    public static function admin(mixed $value): string
    {
        if (! is_string($value) || $value === '' || strlen($value) > 2000) {
            return '/admin';
        }

        $path = (string) parse_url($value, PHP_URL_PATH);
        $query = parse_url($value, PHP_URL_QUERY);

        if (! str_starts_with($value, '/') || str_starts_with($value, '//') || str_contains($value, '\\') || preg_match('/[\x00-\x1f\x7f]/', $value) === 1) {
            return '/admin';
        }

        if ($path !== '/admin' && ! str_starts_with($path, '/admin/')) {
            return '/admin';
        }

        if (str_starts_with($path, '/admin/sign-in') || str_starts_with($path, '/admin/second-factor')) {
            return '/admin';
        }

        return $path.(is_string($query) && $query !== '' ? '?'.$query : '');
    }

    /**
     * Where to go after signing in to the app: any path in the app on this site (the admin's own
     * pages go to /admin), never anywhere else.
     */
    public static function app(mixed $value): string
    {
        if (! is_string($value) || $value === '' || strlen($value) > 2000 || ! str_starts_with($value, '/') || str_starts_with($value, '//')
            || str_contains($value, '\\') || preg_match('/[\x00-\x1f\x7f]/', $value) === 1) {
            return '/';
        }

        $path = (string) parse_url($value, PHP_URL_PATH);

        if ($path === '/admin' || str_starts_with($path, '/admin/')) {
            return self::admin($value);
        }

        // Never back to a sign-in or API address.
        if (preg_match('#^/(api|auth|dev)(/|$)#', $path) === 1) {
            return '/';
        }

        $query = parse_url($value, PHP_URL_QUERY);

        return $path.(is_string($query) && $query !== '' ? '?'.$query : '');
    }
}
