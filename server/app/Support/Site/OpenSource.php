<?php

declare(strict_types=1);

namespace App\Support\Site;

use App\Support\Flags;

/**
 * Whether the site says Stackapella is open source (docs/open-source-plan.md, 5.1): the
 * `open_source` flag, off until the repos are public. While it's off, /open-source, /selfhost and
 * the self-hosting terms aren't there, and no page mentions them.
 */
final class OpenSource
{
    /** The pages it adds, for the sitemap. */
    public const PAGES = ['/open-source', '/selfhost'];

    /**
     * The Attribution Assurance License's credit (LICENSE, 2: the author's name, Stackapella and
     * this address). Fixed in code, never a setting: a self-hosted install keeps it.
     */
    public const CREDIT_URL = 'https://stackapella.com';

    public static function on(): bool
    {
        return app(Flags::class)->on('open_source');
    }

    /**
     * Whether the public pages show the licence's credit: once we say we're open source, and always
     * on a self-hosted install (config/selfhost.php), whatever its flags say.
     */
    public static function credited(): bool
    {
        return (bool) config('selfhost.enabled') || self::on();
    }

    /** The credit's address as it's shown: stackapella.com. */
    public static function creditHost(): string
    {
        return (string) parse_url(self::CREDIT_URL, PHP_URL_HOST);
    }

    /** A link into the public repo: its front page, or a file in it. */
    public static function repo(string $path = ''): string
    {
        $repo = rtrim((string) config('site.source.repo'), '/');

        return $path === '' ? $repo : $repo.'/blob/main/'.ltrim($path, '/');
    }
}
