<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Media\Instruments;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Puts a built instrument on the media CDN (App\Support\Media\Instruments): checks the folder,
 * uploads it as instruments/<name>/<version>/ and makes it current in instruments/index.json.
 * `--current` alone points the index at a version already uploaded (to go back).
 */
final class UploadInstrument extends Command
{
    protected $signature = 'app:instruments:upload {name : The instrument, like "piano"} {version : Like 1, 1.2 or 1.2.3} {folder? : The built folder (with its manifest.json)} {--current : Only make an uploaded version the current one} {--not-current : Upload without changing the index} {--check : Only check the folder}';

    protected $description = 'Upload a built instrument\'s samples to the media CDN, and list it in the instruments index';

    public function handle(): int
    {
        $name = (string) $this->argument('name');
        $version = (string) $this->argument('version');
        $folder = $this->argument('folder');

        try {
            if ($this->option('current')) {
                Instruments::setCurrent($name, $version);
                $this->info("{$name} {$version} is now current in ".Instruments::indexUrl().'.');

                return self::SUCCESS;
            }

            if (! is_string($folder) || $folder === '') {
                $this->error('Give the built folder to upload.');

                return self::FAILURE;
            }

            if ($this->option('check')) {
                $this->info('The folder is fine: '.count(Instruments::files($folder, $name, $version)).' files.');

                return self::SUCCESS;
            }

            $done = Instruments::upload($folder, $name, $version, makeCurrent: ! $this->option('not-current'));
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Uploaded {$done['files']} files. The manifest: {$done['manifest']}.");
        $this->line($this->option('not-current') ? 'The index is unchanged.' : 'The index: '.Instruments::indexUrl().' (cached for 5 minutes).');

        return self::SUCCESS;
    }
}
