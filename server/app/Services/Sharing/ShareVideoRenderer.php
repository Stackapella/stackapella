<?php

declare(strict_types=1);

namespace App\Services\Sharing;

use App\Support\Sharing\Karaoke;
use Closure;
use GdImage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Share as a video (docs/more-features-plan.md, feature 4): a 1080 x 1920 MP4 of a section of the
 * published mix. GD draws every layer in our font (the background with the title and singers, one
 * long strip of the parts' waveforms, each line of words as it's sung, the end card), and ffmpeg
 * only moves and encodes them: the strip scrolls past a now line, the words change at each
 * syllable. So the look is the same from every browser, and ffmpeg needs no text support (no
 * libass or drawtext), only overlay and H.264.
 */
final class ShareVideoRenderer
{
    public const WIDTH = 1080;

    public const HEIGHT = 1920;

    /** How fast the waveforms scroll, in pixels a second (8 per 50 ms outline byte). */
    private const PIXELS_PER_SECOND = 160;

    private const WAVE_TOP = 600;

    private const WAVE_HEIGHT = 620;

    // Above the bottom fifth, where TikTok and Reels put their own captions and buttons.
    private const WORDS_TOP = 1270;

    private const WORDS_HEIGHT = 340;

    /**
     * @param  array{audio: string, out: string, start: float, end: float, look: string, title: string, singers: string, song: string, link: string, tracks: list<array{name: string, color: string, peaks: string}>, lines: list<list<array{text: string, start: float, end: float, joins: bool}>>}  $job  `lines` already relative to `start`
     * @param  Closure(float): void  $progress  called with 0 to 1 as it goes
     */
    public function render(array $job, Closure $progress): void
    {
        $dir = sys_get_temp_dir().'/share-video-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($dir);

        try {
            $section = round($job['end'] - $job['start'], 3);
            $endCard = (float) config('sharing.videos.end_card_seconds');
            $total = $section + $endCard;
            $fps = (int) config('sharing.videos.fps');
            $look = Drawing::LOOKS[$job['look']] ?? Drawing::LOOKS['dark'];

            $this->png($this->background($job, $look), "{$dir}/background.png");
            $this->png($this->strip($job, $look, $section), "{$dir}/strip.png");
            $this->png($this->front($look), "{$dir}/front.png");
            $this->png($this->endCard($job, $look), "{$dir}/end.png");
            $words = $job['lines'] !== [] ? $this->words($job['lines'], $look, $section, $dir) : null;
            $progress(0.1);

            $inputs = ['-loop', '1', '-framerate', (string) $fps, '-t', (string) $total, '-i', "{$dir}/background.png", '-i', "{$dir}/strip.png", '-i', "{$dir}/front.png", '-i', "{$dir}/end.png"];
            $speed = self::PIXELS_PER_SECOND;
            $filters = [
                "[1:v]loop=loop=-1:size=1:start=0,setpts=N/{$fps}/TB[strip]",
                "[2:v]loop=loop=-1:size=1:start=0,setpts=N/{$fps}/TB[front]",
                "[3:v]loop=loop=-1:size=1:start=0,setpts=N/{$fps}/TB[end]",
                "[0:v][strip]overlay=x='-t*{$speed}':y=".self::WAVE_TOP.':shortest=0:eof_action=repeat[a]',
                '[a][front]overlay=x=0:y='.self::WAVE_TOP.'[b]',
            ];
            $last = 'b';
            $audio = 4;

            if ($words !== null) {
                $inputs = [...$inputs, '-f', 'concat', '-safe', '0', '-i', $words];
                $filters[] = "[4:v]fps={$fps}[words]";
                $filters[] = '[b][words]overlay=x=0:y='.self::WORDS_TOP.":eof_action=pass:enable='lt(t,{$section})'[c]";
                $last = 'c';
                $audio = 5;
            }

            $fadeOut = round(max(0, $section - 0.6), 3);
            $filters[] = "[{$last}][end]overlay=x=0:y=0:enable='gte(t,{$section})'[video]";
            $filters[] = "[{$audio}:a]afade=t=in:d=0.05,afade=t=out:st={$fadeOut}:d=0.6,apad=whole_dur={$total}[audio]";

            $command = [
                'ffmpeg', '-hide_banner', '-nostats', '-loglevel', 'error', '-y',
                ...$inputs,
                '-ss', (string) $job['start'], '-t', (string) $section, '-i', $job['audio'],
                '-filter_complex', implode(';', $filters),
                '-map', '[video]', '-map', '[audio]', '-t', (string) $total,
                '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '23', '-maxrate', '1600k', '-bufsize', '3200k', '-pix_fmt', 'yuv420p', '-r', (string) $fps,
                '-c:a', 'aac', '-b:a', '128k', '-ac', '2', '-movflags', '+faststart',
                '-progress', 'pipe:1',
                $job['out'],
            ];

            $result = Process::timeout(280)->run($command, static function (string $type, string $output) use ($progress, $total): void {
                if ($type === 'out' && preg_match_all('/out_time_us=(\d+)/', $output, $matches) > 0) {
                    $seconds = (int) end($matches[1]) / 1_000_000;
                    $progress(0.1 + 0.88 * min(1, $seconds / max(0.1, $total)));
                }
            });

            if (! $result->successful() || ! is_file($job['out']) || filesize($job['out']) === 0) {
                throw new RuntimeException('ffmpeg could not make the video: '.mb_strimwidth($result->errorOutput(), 0, 500, '...'));
            }
        } finally {
            File::deleteDirectory($dir);
        }
    }

    /**
     * @param  array{title: string, singers: string, song: string}  $job
     * @param  array{background: string, text: string, muted: string, line: string}  $look
     */
    private function background(array $job, array $look): GdImage
    {
        $image = Drawing::canvas(self::WIDTH, self::HEIGHT, $look['background']);
        $bold = Drawing::font('bold');
        $medium = Drawing::font('medium');

        $y = 250;
        foreach (Drawing::wrap($job['title'], 66, $bold, self::WIDTH - 160, 2) as $line) {
            Drawing::text($image, $line, 66, 80, $y, Drawing::color($image, $look['text']), $bold);
            $y += 88;
        }

        Drawing::text($image, Drawing::wrap($job['singers'], 38, $medium, self::WIDTH - 160, 1)[0] ?? '', 38, 80, $y + 10, Drawing::color($image, $look['text']), $medium);

        if ($job['song'] !== '') {
            Drawing::text($image, Drawing::wrap($job['song'], 32, $medium, self::WIDTH - 160, 1)[0] ?? '', 32, 80, $y + 64, Drawing::color($image, $look['muted']), $medium);
        }

        Drawing::text($image, 'Stackapella', 34, 80, self::HEIGHT - 70, Drawing::color($image, $look['muted']), $bold);

        return $image;
    }

    /**
     * Every part's waveform for the section, side by side in lanes, starting half a screen in so
     * the section's first moment sits on the now line at the start.
     *
     * @param  array{start: float, tracks: list<array{name: string, color: string, peaks: string}>}  $job
     * @param  array{background: string, text: string, muted: string, line: string}  $look
     */
    private function strip(array $job, array $look, float $section): GdImage
    {
        $half = intdiv(self::WIDTH, 2);
        $width = $half * 2 + (int) ceil($section * self::PIXELS_PER_SECOND);
        $image = Drawing::canvas($width, self::WAVE_HEIGHT);
        $tracks = $job['tracks'];
        $lane = intdiv(self::WAVE_HEIGHT, max(1, count($tracks)));
        $from = (int) floor($job['start'] * 20);
        $count = (int) ceil($section * 20);
        $step = intdiv(self::PIXELS_PER_SECOND, 20);

        foreach ($tracks as $i => $track) {
            $outline = (string) base64_decode($track['peaks'], true);
            Drawing::bars($image, $outline, $half, $i * $lane + intdiv($lane, 2), (int) round($lane * 0.42), $step, $step - 3, Drawing::color($image, $track['color']), $from, $count);
        }

        return $image;
    }

    /**
     * What sits over the moving waveforms: the now line, and the part already sung faded back.
     *
     * @param  array{background: string, text: string, muted: string, line: string}  $look
     */
    private function front(array $look): GdImage
    {
        $image = Drawing::canvas(self::WIDTH, self::WAVE_HEIGHT);
        $half = intdiv(self::WIDTH, 2);
        imagefilledrectangle($image, 0, 0, $half - 1, self::WAVE_HEIGHT, Drawing::color($image, $look['background'], 50));
        imagefilledrectangle($image, $half - 2, 0, $half + 1, self::WAVE_HEIGHT, Drawing::color($image, $look['line'], 20));

        return $image;
    }

    /**
     * @param  array{title: string, link: string}  $job
     * @param  array{background: string, text: string, muted: string, line: string}  $look
     */
    private function endCard(array $job, array $look): GdImage
    {
        $image = Drawing::canvas(self::WIDTH, self::HEIGHT, $look['background']);
        $bold = Drawing::font('bold');
        $medium = Drawing::font('medium');
        $made = __('sharing.video.made_with');
        Drawing::text($image, $made, 58, intdiv(self::WIDTH - Drawing::width($made, 58, $bold), 2), 880, Drawing::color($image, $look['text']), $bold);
        $link = Drawing::wrap($job['link'], 40, $medium, self::WIDTH - 120, 1)[0] ?? '';
        Drawing::text($image, $link, 40, intdiv(self::WIDTH - Drawing::width($link, 40, $medium), 2), 980, Drawing::color($image, $look['muted']), $medium);

        return $image;
    }

    /**
     * One picture for each moment the words change (each syllable as it's sung), and ffmpeg's
     * concat list that shows each until the next.
     *
     * @param  list<list<array{text: string, start: float, end: float, joins: bool}>>  $lines
     * @param  array{background: string, text: string, muted: string, line: string}  $look
     */
    private function words(array $lines, array $look, float $section, string $dir): string
    {
        // [when, line, the last syllable sung (-1 before the first)]
        $states = [[0.0, 0, -1]];
        foreach ($lines as $l => $line) {
            foreach ($line as $s => $syllable) {
                $states[] = [$syllable['start'], $l, $s];
            }
        }

        $list = "ffconcat version 1.0\n";
        $file = '';
        $count = count($states);

        for ($i = 0; $i < $count; $i++) {
            [$at, $l, $s] = $states[$i];
            $next = $states[$i + 1][0] ?? $section;
            $duration = round($next - $at, 3);

            if ($duration <= 0) {
                continue;
            }

            $file = sprintf('%s/words-%04d.png', $dir, $i);
            $this->png($this->wordsFrame($lines[$l], $lines[$l + 1] ?? null, $s, $look), $file);
            $list .= "file '".basename($file)."'\nduration {$duration}\n";
        }

        // The concat format shows the last file's duration only when it's listed once more.
        $list .= "file '".basename($file)."'\n";
        File::put("{$dir}/words.txt", $list);

        return "{$dir}/words.txt";
    }

    /**
     * @param  list<array{text: string, start: float, end: float, joins: bool}>  $line
     * @param  list<array{text: string, start: float, end: float, joins: bool}>|null  $next
     * @param  array{background: string, text: string, muted: string, line: string}  $look
     */
    private function wordsFrame(array $line, ?array $next, int $through, array $look): GdImage
    {
        $image = Drawing::canvas(self::WIDTH, self::WORDS_HEIGHT);
        $bold = Drawing::font('bold');
        $full = Karaoke::text($line);
        $size = 60.0;

        while ($size > 30 && Drawing::width($full, $size, $bold) > self::WIDTH - 120) {
            $size -= 2;
        }

        $x = intdiv(self::WIDTH - Drawing::width($full, $size, $bold), 2);
        // The line in the muted colour, then what's been sung over it in the text colour.
        Drawing::text($image, $full, $size, $x, 120, Drawing::color($image, $look['muted']), $bold);

        if ($through >= 0) {
            Drawing::text($image, Karaoke::text($line, $through), $size, $x, 120, Drawing::color($image, $look['text']), $bold);
        }

        if ($next !== null) {
            $upcoming = Karaoke::text($next);
            $small = 40.0;
            while ($small > 24 && Drawing::width($upcoming, $small, $bold) > self::WIDTH - 160) {
                $small -= 2;
            }
            Drawing::text($image, $upcoming, $small, intdiv(self::WIDTH - Drawing::width($upcoming, $small, $bold), 2), 230, Drawing::color($image, $look['muted'], 40), $bold);
        }

        return $image;
    }

    private function png(GdImage $image, string $path): void
    {
        imagealphablending($image, false);
        imagesavealpha($image, true);

        if (! imagepng($image, $path, 1)) {
            throw new RuntimeException("GD could not write {$path}.");
        }
    }
}
