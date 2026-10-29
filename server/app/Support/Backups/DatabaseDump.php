<?php

declare(strict_types=1);

namespace App\Support\Backups;

use Illuminate\Support\Facades\DB;
use Pdo\Mysql;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * mysqldump and mysql through the configured connection (a port of iaw's). The password goes in
 * through the environment (MYSQL_PWD), never on the command line where `ps` would show it.
 */
final class DatabaseDump
{
    /**
     * The database to a gzipped file.
     */
    public function dump(string $path): void
    {
        $config = $this->connection();
        $gzip = gzopen($path, 'wb6');

        if ($gzip === false) {
            throw new RuntimeException("Can't write {$path}.");
        }

        $process = new Process([
            // MariaDB's client (Debian's default-mysql-client, our Aptfile) has no --set-gtid-purged.
            self::bin('mysqldump'), '--single-transaction', '--quick', ...(self::mariadb() ? [] : ['--set-gtid-purged=OFF']), '--no-tablespaces', '--routines',
            '--host='.$config['host'], '--port='.$config['port'], '--user='.$config['username'],
            ...($config['ssl_ca'] !== null ? ['--ssl-ca='.$config['ssl_ca']] : []),
            $config['database'],
        ], null, ['MYSQL_PWD' => $config['password']], null, 3600);
        $process->run(function (string $type, string $output) use ($gzip): void {
            if ($type === Process::OUT) {
                gzwrite($gzip, $output);
            }
        });
        gzclose($gzip);

        if (! $process->isSuccessful()) {
            @unlink($path);

            throw new RuntimeException('mysqldump failed: '.trim($process->getErrorOutput()));
        }
    }

    /**
     * A .sql.gz file into a database on the same server (made if it's missing).
     */
    public function load(string $path, string $database): void
    {
        $config = $this->connection();
        // Streamed to mysql as it's read, never held in memory whole.
        $stream = fopen('compress.zlib://'.$path, 'rb');

        if ($stream === false) {
            throw new RuntimeException("Can't read {$path}.");
        }

        $process = new Process([
            self::bin('mysql'), '--host='.$config['host'], '--port='.$config['port'], '--user='.$config['username'], '--default-character-set=utf8mb4',
            ...($config['ssl_ca'] !== null ? ['--ssl-ca='.$config['ssl_ca']] : []),
            $database,
        ], null, ['MYSQL_PWD' => $config['password']], $stream, 3600);

        try {
            $process->run();
        } finally {
            fclose($stream);
        }

        if (! $process->isSuccessful()) {
            throw new RuntimeException('mysql failed: '.trim($process->getErrorOutput()));
        }
    }

    /**
     * A MySQL client tool, from MYSQL_CLIENT_PATH when it isn't on the PATH (a laptop's MySQL).
     */
    private static function bin(string $tool): string
    {
        $folder = (string) config('database.mysql_client_path');

        return $folder !== '' ? rtrim($folder, '/').'/'.$tool : $tool;
    }

    private static function mariadb(): bool
    {
        $process = new Process([self::bin('mysqldump'), '--version']);
        $process->run();

        return str_contains(strtolower($process->getOutput()), 'mariadb');
    }

    public static function toolsInstalled(): bool
    {
        $process = new Process([self::bin('mysqldump'), '--version']);
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * @return array{host: string, port: string, username: string, password: string, database: string, ssl_ca: string|null}
     */
    public function connection(): array
    {
        $config = (array) config('database.connections.'.DB::getDefaultConnection());
        $ca = $config['options'][Mysql::ATTR_SSL_CA] ?? null;

        return [
            'host' => (string) ($config['host'] ?? '127.0.0.1'),
            'port' => (string) ($config['port'] ?? '3306'),
            'username' => (string) ($config['username'] ?? 'root'),
            'password' => (string) ($config['password'] ?? ''),
            'database' => (string) ($config['database'] ?? ''),
            'ssl_ca' => is_string($ca) && $ca !== '' ? $ca : null,
        ];
    }
}
