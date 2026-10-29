<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use Tests\TestCase;

/**
 * A shared idea's page and a virtual choir's page, rendered from the backend's view data
 * (SingTogetherPagesController): the title as the h1, each credit's name and part, a profile link
 * when there is one, the way into the app, and never a single voice's audio.
 */
final class TogetherPagesTest extends TestCase
{
    public function test_an_ideas_page_shows_its_parts_its_voices_and_sing_a_part(): void
    {
        $html = view('public.idea', [
            'idea' => [
                'id' => 'abc', 'title' => 'Bus Stop Hum', 'note' => 'Hummed it at the bus stop.', 'writer' => ['handle' => 'sam', 'name' => 'Sam', 'avatarUrl' => null],
                'voicing' => 4, 'voices' => 1, 'complete' => false, 'status' => 'open', 'createdAt' => '2026-10-27T10:00:00Z',
                'parts' => [
                    ['key' => 'soprano', 'name' => 'Melody', 'range' => ['low' => 62, 'high' => 74], 'voices' => 1],
                    ['key' => 'alto', 'name' => 'Alto', 'range' => ['low' => 55, 'high' => 67], 'voices' => 0],
                ],
                'credits' => [['partKey' => 'soprano', 'singer' => ['handle' => 'ada', 'name' => 'Ada <b>', 'avatarUrl' => null]]],
            ],
            'meta' => ['title' => 'Bus Stop Hum, a tune by Sam', 'description' => 'A tune by Sam.', 'url' => 'https://stackapella.test/ideas/abc', 'image' => 'https://stackapella.test/icons/og-landing.png'],
            'links' => ['sing' => '/?idea=abc&ref=idea-abc', 'app' => '/?ref=idea-abc'],
        ])->render();

        $this->assertStringContainsString('<h1 id="page-h" translate="no">Bus Stop Hum, a tune by Sam</h1>', $html);
        $this->assertStringContainsString('data-part="soprano"', $html);
        $this->assertStringContainsString('Ada &lt;b&gt;', $html);
        $this->assertStringContainsString('/@ada', $html);
        $this->assertStringContainsString(e(__('pages.together.open')), $html);
        $this->assertStringContainsString('href="/?idea=abc&amp;ref=idea-abc"', $html);
        $this->assertStringContainsString('D4 to D5', $html);
        $this->assertStringNotContainsString('<audio', $html);
    }

    public function test_a_choirs_page_plays_the_mix_only_and_counts_the_unnamed(): void
    {
        $html = view('public.choir', [
            'choir' => [
                'song' => ['id' => 'amazing-grace', 'title' => 'Amazing Grace', 'version' => 1], 'bpm' => 80, 'key' => 'G', 'voices' => 3,
                'mixUrl' => 'https://media.test/choirs/amazing-grace.m4a', 'mixedAt' => '2026-10-27T10:00:00Z',
                'parts' => [['key' => 'alto', 'name' => 'Alto', 'voices' => 3]],
                'credits' => [['partKey' => 'alto', 'singer' => ['handle' => null, 'name' => 'Grace', 'avatarUrl' => null]]],
            ],
            'meta' => ['title' => 'Amazing Grace, sung together', 'description' => '3 singers have sung Amazing Grace together.', 'url' => 'https://stackapella.test/choirs/amazing-grace', 'image' => 'https://stackapella.test/icons/og-landing.png', 'audio' => 'https://media.test/choirs/amazing-grace.m4a'],
            'links' => ['sing' => '/?choir=amazing-grace&ref=choir-amazing-grace', 'song' => '/songs/amazing-grace', 'app' => '/?ref=choir-amazing-grace'],
        ])->render();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertSame(1, substr_count($html, '<audio'));
        $this->assertStringContainsString('src="https://media.test/choirs/amazing-grace.m4a"', $html);
        $this->assertStringContainsString('data-part="alto"', $html);
        $this->assertStringContainsString(e(__('pages.together.more')), $html);
        $this->assertStringNotContainsString('/@', $html);
    }
}
