<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Ops\Readiness;
use Illuminate\Console\Command;

/**
 * php artisan app:doctor: what's running where (versions, build, drivers), then every readiness
 * check. For a person at a terminal; the scheduled one is app:readiness --alert.
 */
final class Doctor extends Command
{
    protected $signature = 'app:doctor';

    protected $description = 'Show the build, the versions and the drivers, then every readiness check';

    public function handle(Readiness $readiness): int
    {
        $this->table(['What', 'Value'], [
            ['Environment', (string) app()->environment()],
            ['Build', (string) config('app.build') ?: 'local'],
            ['PHP', PHP_VERSION],
            ['Laravel', app()->version()],
            ['Database', (string) config('database.default')],
            ['Queue', (string) config('queue.default')],
            ['Cache', (string) config('cache.default')],
            ['Mail', (string) config('mail.default')],
            ['Disks', 'media='.config('filesystems.disks.media.driver').', private='.config('filesystems.disks.private.driver').', backup='.config('filesystems.disks.backup.driver')],
        ]);

        return $this->call('app:readiness');
    }
}
