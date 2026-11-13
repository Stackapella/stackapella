<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BetaFixtures;
use Tests\TestCase;

/**
 * The content's own stories on their song pages: a quote or an "&c." in the words reads as written, never as a
 * double-escaped "&quot;" or "&amp;c." (game's scan, 2026-11-13: nine pages showed them).
 */
final class StoryWordsEscapeTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    /** @return array<string, array{string}> */
    public static function songs(): array
    {
        return ['Good King Wenceslas' => ['good-king-wenceslas'], 'Blow the Man Down' => ['blow-the-man-down'], 'Greensleeves' => ['greensleeves']];
    }

    #[DataProvider('songs')]
    public function test_a_storys_words_are_escaped_once(string $slug): void
    {
        config(['app.is_nexus' => true, 'app.coming_soon' => false]);
        $this->catalogue();
        $response = $this->get('/songs/'.$slug);
        if ($response->status() === 404) {
            $this->markTestSkipped($slug.' is not open in this content');
        }
        $html = (string) $response->assertOk()->getContent();
        $this->assertStringNotContainsString('&amp;quot;', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
        $this->assertStringNotContainsString('&amp;#', $html);
    }
}
