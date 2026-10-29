<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Services\Audio\Ffmpeg;
use Tests\Support\Wav;
use Tests\TestCase;

/**
 * An upload is read as the WAV it claims to be, and only from the local file (docs/security-review.md):
 * a playlist named .wav is no audio at all, so ffmpeg never follows its URLs.
 */
final class FfmpegInputTest extends TestCase
{
    public function test_a_real_wav_is_read_and_a_playlist_named_wav_is_not(): void
    {
        $dir = sys_get_temp_dir().'/ffmpeg-input-'.bin2hex(random_bytes(4));
        mkdir($dir);

        try {
            file_put_contents($dir.'/take.wav', Wav::tone(0.5));
            $probe = app(Ffmpeg::class)->probe($dir.'/take.wav');
            $this->assertSame(['wav', 'pcm_s16le', 1, 48000], [$probe['format'] ?? null, $probe['codec'] ?? null, $probe['channels'] ?? null, $probe['sampleRate'] ?? null]);

            file_put_contents($dir.'/sneaky.wav', "#EXTM3U\n#EXT-X-TARGETDURATION:10\n#EXTINF:10,\nhttp://169.254.169.254/latest/meta-data/\n#EXT-X-ENDLIST\n");
            $this->assertNull(app(Ffmpeg::class)->probe($dir.'/sneaky.wav'));
        } finally {
            array_map('unlink', glob($dir.'/*') ?: []);
            rmdir($dir);
        }
    }
}
