<?php

declare(strict_types=1);

namespace Tests;

use Dotenv\Dotenv;
use PDO;
use RuntimeException;

/**
 * Gives every test run its own MySQL database (iaw's), so two sessions never drop each other's
 * tables. The base name comes from DB_TEST_DATABASE (CI's env, .env.testing or .env), plus a short
 * token from TEST_DB_TOKEN or CLAUDE_CODE_SESSION_ID. RefreshDatabase then migrates it.
 *
 * Refuses to run against anything whose name doesn't contain "test".
 */
final class TestDatabase
{
    public static function bootstrap(string $basePath): void
    {
        $values = [];
        foreach (['.env', '.env.testing'] as $file) {
            if (is_file($basePath.'/'.$file)) {
                $values = [...$values, ...Dotenv::parse((string) file_get_contents($basePath.'/'.$file))];
            }
        }

        $get = static fn (string $key, string $default): string => ($v = getenv($key)) !== false && $v !== '' ? $v : (string) (($values[$key] ?? '') !== '' ? $values[$key] : $default);

        $base = $get('DB_TEST_DATABASE', 'stackapella_test');
        $token = self::token();
        $database = $token === null ? $base : $base.'_'.$token;

        if (! str_contains($database, 'test')) {
            throw new RuntimeException("Refusing to run tests against [{$database}]: the name must contain \"test\".");
        }

        $settings = [
            'DB_DATABASE' => $database,
            'DB_HOST' => $get('DB_HOST', '127.0.0.1'),
            'DB_PORT' => $get('DB_PORT', '3306'),
            'DB_USERNAME' => $get('DB_USERNAME', 'root'),
            'DB_PASSWORD' => ($v = getenv('DB_PASSWORD')) !== false ? $v : (string) ($values['DB_PASSWORD'] ?? ''),
        ];

        $pdo = new PDO(sprintf('mysql:host=%s;port=%s', $settings['DB_HOST'], $settings['DB_PORT']), $settings['DB_USERNAME'], $settings['DB_PASSWORD'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE DATABASE IF NOT EXISTS `'.str_replace('`', '', $database).'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

        foreach ($settings as $key => $value) {
            putenv($key.'='.$value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    private static function token(): ?string
    {
        foreach (['TEST_DB_TOKEN' => 16, 'CLAUDE_CODE_SESSION_ID' => 8] as $variable => $length) {
            $token = substr(strtolower((string) preg_replace('/[^A-Za-z0-9]/', '', (string) getenv($variable))), 0, $length);

            if ($token !== '') {
                return $token;
            }
        }

        return null;
    }
}
