<?php

declare(strict_types=1);

namespace Tests\Unit\Pages;

use App\Support\Pages\YodelingPage;
use PHPUnit\Framework\TestCase;

/**
 * The Yodeling course page's drawings (App\Support\Pages\YodelingPage): the call is the course's own hook, drawn in the
 * voice each note is sung in, its echoes later; the alphorn's notes sit at their true cents, the 11th between F and F
 * sharp; and the signpost says the course's real size.
 */
final class YodelingPageTest extends TestCase
{
    public function test_the_hook_is_the_course_s_own(): void
    {
        $file = dirname(__DIR__, 4).'/web/course/plus/yodel/course.json';
        if (! is_file($file)) {
            $this->markTestSkipped('The course content is not linked.');
        }

        $course = (array) json_decode((string) file_get_contents($file), true);
        $notes = null;
        foreach ((array) ($course['units'][0]['lessons'] ?? []) as $lesson) {
            if (is_array($lesson) && ($lesson['id'] ?? null) === 'yodel-1-hear') {
                // The lesson's first step plays the hook to learn it.
                $notes = array_map(static fn (array $n): array => [$n['beat'], $n['length'], $n['step'], ($n['voice'] ?? '') === 'head'], (array) ($lesson['steps'][0]['listen']['notes'] ?? []));
            }
        }

        $this->assertSame($notes, YodelingPage::HOOK);
    }

    public function test_the_call_jumps_into_each_voice_on_its_beat(): void
    {
        $call = YodelingPage::call(230, 110);

        $this->assertSame(['chest', 'head', 'chest'], array_column($call, 1));
        $this->assertSame('M230 152 H340', $call[0][0]);
        $this->assertSame('M340 152 V72 M340 72 H450', $call[1][0]);
        $this->assertSame('M450 72 V152 M450 152 H670', $call[2][0]);
        $this->assertSame([0.0, 0.833, 1.667], array_column($call, 2));

        // The second echo: lower, and four beats after the call.
        $echo = YodelingPage::call(1190, 40, .5, 2);
        $this->assertSame('M1230 152 V112 M1230 112 H1270', $echo[1][0]);
        $this->assertSame(3.333, $echo[0][2]);
    }

    public function test_the_alphorn_s_notes_by_their_cents(): void
    {
        $this->assertSame([8 => 0, 9 => 204, 10 => 386, 11 => 551, 12 => 702], YodelingPage::hornNotes());
        $keys = YodelingPage::keys();
        $this->assertTrue($keys['F'] < YodelingPage::hornNotes()[11] && YodelingPage::hornNotes()[11] < $keys['F♯']);
    }

    public function test_the_signpost_says_the_course_s_size(): void
    {
        $this->assertSame('16 lessons · about 30 min', YodelingPage::size(['lessons' => 16, 'minutes' => 30], ':lessons lessons · about :minutes min', ':n lessons'));
        $this->assertSame('16 lessons', YodelingPage::size(['lessons' => 16, 'minutes' => null], ':lessons lessons · about :minutes min', ':n lessons'));
        $this->assertSame([false, false, true, true], array_map(YodelingPage::mountain(...), [1, 2, 3, 4]));
    }
}
