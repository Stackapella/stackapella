<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Site\Showreel;
use App\Support\Site\ShowreelUpload;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Puts the landing's film on the media CDN (App\Support\Site\ShowreelUpload): checks the renders
 * against the manifest in git, then uploads them as showreel/<version>/. The renders aren't in git:
 * `--from` is the folder they were rendered to.
 */
final class UploadShowreel extends Command
{
    protected $signature = 'app:showreel:upload {--from= : The folder of renders (default server/public/videos/showreel)} {--check : Only check the files}';

    protected $description = 'Upload the landing\'s film to the media CDN';

    public function handle(): int
    {
        $from = $this->option('from');
        $folder = is_string($from) && $from !== '' ? $from : dirname(Showreel::manifest());

        try {
            if ($this->option('check')) {
                $files = ShowreelUpload::files($folder);
                $this->info('The render is fine: '.count($files).' files, version '.Showreel::version().'.');
                $this->line('It would go to '.ShowreelUpload::baseUrl().'/'.Showreel::version().'/.');

                return self::SUCCESS;
            }

            $done = ShowreelUpload::upload($folder);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Uploaded {$done['uploaded']} files ({$done['skipped']} already there), version ".Showreel::version().'.');
        $this->line("Set SHOWREEL_URL={$done['url']} (once; a new render is a new folder under it).");

        return self::SUCCESS;
    }
}
