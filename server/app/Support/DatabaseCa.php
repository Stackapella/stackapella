<?php

declare(strict_types=1);

namespace App\Support;

/**
 * DigitalOcean's Managed MySQL only takes TLS connections signed by the cluster's own CA. DB_CA_CERT
 * holds that CA (public, base64); this writes it to a file once per container and gives PDO its path
 * (iaw's). Read from config/database.php, so it works in every component and the migrate job.
 */
final class DatabaseCa
{
    /**
     * @param  mixed  $base64  the setting as env() reads it (a string, or nothing)
     */
    public static function path(mixed $base64): ?string
    {
        if (! is_string($base64) || trim($base64) === '') {
            return null;
        }

        $pem = base64_decode(trim($base64), true);

        if ($pem === false || ! str_contains($pem, 'BEGIN CERTIFICATE')) {
            return null;
        }

        $path = storage_path('framework/db-ca-'.substr(hash('sha256', $pem), 0, 16).'.pem');

        if (! is_file($path)) {
            // Written beside, then moved, so a process starting at the same moment never reads half a file.
            $temporary = $path.'.'.getmypid();
            file_put_contents($temporary, $pem);
            rename($temporary, $path);
        }

        return $path;
    }
}
