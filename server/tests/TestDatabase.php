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
        // A parallel run's worker (`php artisan test --parallel --without-databases`, paratest's TEST_TOKEN): its own
        // database, chosen here before the app boots. Laravel's own switch happens after boot and purges the
        // connection, which left services resolved at boot (the cache the rate limits use) writing outside each test's
        // transaction, into a database every worker shared.
        $worker = $_SERVER['TEST_TOKEN'] ?? getenv('TEST_TOKEN');
        if (! empty($_SERVER['LARAVEL_PARALLEL_TESTING'] ?? getenv('LARAVEL_PARALLEL_TESTING')) && is_string($worker) && preg_match('/^\d+$/', $worker) === 1) {
            $database .= '_w'.$worker;
            // And Laravel's own switch stays off: `--without-databases` never reaches paratest's workers, and its
            // switch would leave the cache on this database while each test ran on another (a probe, 2026-11-12).
            $_SERVER['LARAVEL_PARALLEL_TESTING_WITHOUT_DATABASES'] = '1';
        }

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

    /** The table that says the database is migrated and clean, and from which migrations (not one of the app's). */
    private const MARKER = 'zz_test_schema';

    /** @var PDO|null A connection of its own: what it writes never joins (or rolls back with) a test's transaction. */
    private static ?PDO $own = null;

    /** The database $own is on: in a parallel run each worker switches to its own (`<base>_test_<n>`) after booting. */
    private static ?string $ownDatabase = null;

    private static bool $forgotten = false;

    /**
     * Whether this run may skip migrating afresh: the last run that migrated left this database's schema as the
     * migrations make it now (the same files, byte for byte), and nothing since changed its schema or left rows behind
     * (forget() takes the marker away when something does).
     */
    public static function isCurrent(): bool
    {
        try {
            $statement = self::own()->query('select fingerprint from `'.self::MARKER.'` where id = 1');
            $row = $statement !== false ? $statement->fetch(PDO::FETCH_ASSOC) : false;
        } catch (\PDOException) {
            return false;
        }

        return is_array($row) && hash_equals(self::fingerprint(), (string) $row['fingerprint']);
    }

    /** After migrating afresh: the database is as the migrations make it. */
    public static function markCurrent(): void
    {
        self::own()->exec('create table if not exists `'.self::MARKER.'` (id tinyint unsigned primary key, fingerprint char(64) not null)');
        self::own()->prepare('replace into `'.self::MARKER.'` (id, fingerprint) values (1, ?)')->execute([self::fingerprint()]);
        self::$forgotten = false;
    }

    /** Something changed the schema or kept rows: the next run migrates afresh. */
    public static function forget(): void
    {
        if (self::$forgotten) {
            return;
        }

        try {
            self::own()->exec('delete from `'.self::MARKER.'`');
        } catch (\PDOException) {
            // No marker, nothing to forget.
        }
        self::$forgotten = true;
    }

    /** The migrations as they are: every file's name and bytes. */
    private static function fingerprint(): string
    {
        static $fingerprint = null;

        if ($fingerprint === null) {
            $files = glob(dirname(__DIR__).'/database/migrations/*.php') ?: [];
            sort($files);
            $fingerprint = hash('sha256', implode("\0", array_map(static fn (string $file): string => basename($file)."\0".hash_file('sha256', $file), $files)));
        }

        return $fingerprint;
    }

    private static function own(): PDO
    {
        $config = (array) config('database.connections.'.config('database.default'));
        // Always the database the tests are on now: a worker's own once Laravel has switched to it, never the run's base.
        if (self::$own === null || self::$ownDatabase !== ($config['database'] ?? null)) {
            self::$ownDatabase = isset($config['database']) ? (string) $config['database'] : null;
            self::$own = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s', $config['host'] ?? '127.0.0.1', $config['port'] ?? '3306', $config['database'] ?? ''), (string) ($config['username'] ?? ''), (string) ($config['password'] ?? ''), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        }

        return self::$own;
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
