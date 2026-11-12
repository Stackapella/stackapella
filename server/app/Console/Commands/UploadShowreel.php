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
    protected $signature = 'app:showreel:upload {--from= : The folder of renders (default server/public/videos/showreel)} {--check : Only check the files} {--lang=en : The film\'s language: en, or a language with its own manifest (showreel/<lang>/)}';

    protected $description = 'Upload the landing\'s film to the media CDN';

    public function handle(): int
    {
        $from = $this->option('from');
        $language = (string) $this->option('lang');
        if (preg_match('/^[a-z]{2}$/', $language) !== 1 || Showreel::language($language) !== $language) {
            $this->error("There is no film in {$language}: commit server/public/videos/showreel/{$language}/manifest.json first.");

            return self::FAILURE;
        }
        $folder = is_string($from) && $from !== '' ? $from : dirname(Showreel::manifest($language));

        try {
            if ($this->option('check')) {
                $files = ShowreelUpload::files($folder, $language);
                $this->info('The render is fine: '.count($files).' files, version '.Showreel::version($language).'.');
                $this->line('It would go to '.ShowreelUpload::baseUrl().'/'.Showreel::version($language).'/.');

                return self::SUCCESS;
            }

            $done = ShowreelUpload::upload($folder, $language);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Uploaded {$done['uploaded']} files ({$done['skipped']} already there), version ".Showreel::version($language).'.');
        $this->line("Set SHOWREEL_URL={$done['url']} (once; a new render is a new folder under it).");

        return self::SUCCESS;
    }
}
