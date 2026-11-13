<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\SongAudio\UploadSongAudio;
use App\Support\SongAudio\SongAudioFiles;
use Illuminate\Console\Command;

/**
 * Puts a folder of whole-song renders (the showreel agent's, with its manifest.json) on the media CDN and writes the
 * committed manifest; land that file after, and the pages play them once it's deployed.
 */
final class UploadSongAudioCommand extends Command
{
    protected $signature = 'app:song-audio:upload {folder : The renders folder, with manifest.json} {--check : Only check the files against the manifest} {--local : Upload to a local media disk (tests, a dev machine)}';

    protected $description = 'Upload the whole-song renders a manifest lists, each checked by its bytes and sha256';

    public function handle(UploadSongAudio $upload): int
    {
        // The keys alone leave the media disk local: the first upload (2026-11-13) put 206 files on the Mac, and the CDN
        // answered 403 for each. Uploading for the site needs the Spaces disk said outright.
        $driver = (string) config('filesystems.disks.media.driver');
        if (! $this->option('check') && ! $this->option('local') && $driver !== 's3') {
            $this->error("The media disk is {$driver}, not the CDN: set MEDIA_DISK=spaces_media and CDN_URL (with the Spaces keys), or pass --local.");

            return self::FAILURE;
        }
        try {
            $result = $upload->handle(rtrim((string) $this->argument('folder'), '/'), (bool) $this->option('check'));
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        foreach ($result['skipped'] as $slug => $why) {
            $this->warn("{$slug}: skipped, {$why}");
        }
        $this->info($this->option('check')
            ? count($result['songs']).' songs check out, '.count($result['skipped']).' skipped.'
            : "{$result['uploaded']} files uploaded, {$result['unchanged']} already there, ".count($result['skipped']).' songs skipped; '.count(SongAudioFiles::manifest()).' songs in '.SongAudioFiles::MANIFEST.'.');

        return $result['skipped'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
