<?php

declare(strict_types=1);

namespace App\Support\Trust;

/**
 * The lists kept in the repo, never fetched from anywhere (resources/data): the most common
 * passwords and disposable email domains. Each is read once per process.
 */
final class WordLists
{
    /** @var array<string, array<string, true>> */
    private static array $lists = [];

    public static function isCommonPassword(string $password): bool
    {
        return isset(self::load('common-passwords')[strtolower($password)]);
    }

    /**
     * A disposable address: its domain, or any domain above it, is on the list.
     */
    public static function isDisposableEmail(string $email): bool
    {
        $domain = strtolower(trim((string) substr((string) strrchr($email, '@'), 1)));
        $list = self::load('disposable-domains');
        $parts = explode('.', $domain);

        while (count($parts) >= 2) {
            if (isset($list[implode('.', $parts)])) {
                return true;
            }
            array_shift($parts);
        }

        return false;
    }

    /**
     * @return array<string, true>
     */
    private static function load(string $name): array
    {
        if (! isset(self::$lists[$name])) {
            $lines = file(resource_path("data/{$name}.txt"), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            self::$lists[$name] = array_fill_keys(array_map('strtolower', array_filter($lines, static fn (string $line): bool => ! str_starts_with($line, '#'))), true);
        }

        return self::$lists[$name];
    }
}
