<?php

declare(strict_types=1);

namespace Tests\Feature\AiVoices;

use App\Models\Harmony;
use App\Models\OwnChoirPart;
use App\Models\ShareVideo;
use App\Models\TemplateGuide;
use App\Services\Audio\Ffmpeg;
use App\Support\AiVoices\AiMark;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Support\Wav;
use Tests\TestCase;

/**
 * The AI marking in every MP4 the server keeps (EU AI Act Article 50; build plan Brief G): written as the iTunes comment
 * and description, read back before a file is kept, and added to the files made before it (app:ai-marking:existing).
 */
final class AiMarkingTest extends TestCase
{
    use RefreshDatabase;

    /** An AAC made the way the server made AI files before the marking: none in it. */
    private function unmarkedAac(): string
    {
        $wav = (string) tempnam(sys_get_temp_dir(), 'ai-mark-test');
        $aac = $wav.'.m4a';
        file_put_contents($wav, Wav::tone(1.0));
        app(Ffmpeg::class)->encodeMix($wav, $aac, 1);
        $bytes = (string) file_get_contents($aac);
        @unlink($wav);
        @unlink($aac);

        return $bytes;
    }

    /**
     * @return array<string, string>
     */
    private function tags(string $disk, string $key): array
    {
        return app(Ffmpeg::class)->tags(Storage::disk($disk)->path($key));
    }

    public function test_the_marking_is_the_itunes_comment_and_a_description_any_reader_finds(): void
    {
        $this->assertSame('ai_generated=true;digital_source_type=http://cv.iptc.org/newscodes/digitalsourcetype/trainedAlgorithmicMedia;generator=Stackapella voices (SoulX-Singer)', AiMark::line());
        $this->assertTrue(AiMark::mp4Marked(['description' => AiMark::line()]));
        // What the server wrote from 2026-11-23 to 24 still counts.
        $this->assertTrue(AiMark::mp4Marked(['ai_generated' => 'true']));
        $this->assertFalse(AiMark::mp4Marked(['comment' => 'a song', 'description' => 'a song by Ada']));

        $path = Storage::disk('private')->path('x.m4a');
        Storage::disk('private')->put('x.m4a', $this->unmarkedAac());
        $this->expectException(RuntimeException::class);
        app(Ffmpeg::class)->assertMarked($path);
    }

    public function test_files_made_before_the_marking_are_marked_and_check_says_so_first(): void
    {
        Storage::fake('private');
        Storage::fake('media');
        $aac = $this->unmarkedAac();

        $guide = TemplateGuide::factory()->ai()->create();
        Storage::disk('media')->put($guide->media_key, $aac);
        $part = OwnChoirPart::factory()->create(['listen_key' => 'choir/parts/listen-1.m4a']);
        Storage::disk('private')->put((string) $part->media_key, $aac);
        Storage::disk('private')->put('choir/parts/listen-1.m4a', $aac);
        $shared = Harmony::factory()->create(['own_voice_parts' => ['alto']]);
        Storage::disk('private')->put((string) $shared->audio_key, $aac);
        $video = ShareVideo::factory()->ready()->create(['harmony_id' => $shared->id]);
        Storage::disk('private')->put((string) $video->file_key, $aac);
        // A mix with no AI voice is left as it is.
        $plain = Harmony::factory()->create();
        Storage::disk('private')->put((string) $plain->audio_key, $aac);

        $this->artisan('app:ai-marking:existing', ['--check' => true])->expectsOutputToContain('5 without the marking')->assertFailed();
        $this->assertFalse(AiMark::mp4Marked($this->tags('private', (string) $part->media_key)), '--check changes nothing.');

        $this->artisan('app:ai-marking:existing')->expectsOutputToContain('5 marked now')->assertSuccessful();

        // A public guide moves to a new key (the CDN keeps the old one a year); the private files are marked where they are.
        $old = $guide->media_key;
        $guide = TemplateGuide::withAi()->findOrFail($guide->id);
        $this->assertNotSame($old, $guide->media_key);
        Storage::disk('media')->assertMissing($old);
        $this->assertTrue(AiMark::mp4Marked($this->tags('media', $guide->media_key)));
        foreach ([(string) $part->media_key, 'choir/parts/listen-1.m4a', (string) $shared->audio_key, (string) $video->file_key] as $key) {
            $this->assertSame([config('ai_marking.text'), AiMark::line()], [$this->tags('private', $key)['comment'] ?? null, $this->tags('private', $key)['description'] ?? null], $key);
        }
        $this->assertFalse(AiMark::mp4Marked($this->tags('private', (string) $plain->audio_key)));
        // The audio itself is untouched.
        $this->assertSame('aac', app(Ffmpeg::class)->probe(Storage::disk('private')->path((string) $part->media_key), upload: false)['codec'] ?? null);

        $this->artisan('app:ai-marking:existing', ['--check' => true])->expectsOutputToContain('0 without the marking')->assertSuccessful();
    }
}
