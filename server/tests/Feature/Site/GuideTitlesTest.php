<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Http\Controllers\Public\DocumentPagesController;
use App\Support\Flags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every guide's search title is its front matter's `title:` (marketing, 2026-11-24): the <title> and og:title carry it,
 * while the page's heading stays the file's "# " line. Before, the heading replaced the front matter everywhere, so a
 * title set there never reached search.
 */
final class GuideTitlesTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_guide_is_titled_by_its_front_matter_and_headed_by_its_heading(): void
    {
        app(Flags::class)->set('lang_es', 'on', null, 'Spanish checked');
        $guides = DocumentPagesController::guides();
        $this->assertNotSame([], $guides);

        $wrong = [];
        foreach (['en' => '', 'es' => '/es'] as $language => $prefix) {
            foreach ($guides as $slug) {
                $file = (string) config('content.guides')."/{$language}/{$slug}.md";
                if (! is_file($file)) {
                    continue;
                }
                $text = (string) file_get_contents($file);
                preg_match('/^---\n(.*?)\n---\n(.*)$/s', $text, $parts);
                preg_match('/^title:\s*(.+)$/m', $parts[1] ?? '', $title);
                preg_match('/^# (.+)$/m', $parts[2] ?? '', $heading);
                if (! isset($title[1], $heading[1])) {
                    continue;
                }

                $html = (string) $this->get("{$prefix}/guides/{$slug}")->assertOk()->getContent();
                preg_match('#<title>(.*?)</title>#s', $html, $shown);
                if (! str_starts_with(html_entity_decode($shown[1] ?? '', ENT_QUOTES), trim($title[1]))) {
                    $wrong[] = "{$language} {$slug}: <title> is \"".($shown[1] ?? '')."\", not \"{$title[1]}\"";
                }
                if (! str_contains($html, '<meta property="og:title" content="'.e(trim($title[1])).'">')) {
                    $wrong[] = "{$language} {$slug}: og:title isn't the front matter's";
                }
                if (! str_contains($html, '<h1 id="page-h">'.e(trim($heading[1])).'</h1>')) {
                    $wrong[] = "{$language} {$slug}: the H1 isn't the heading";
                }
            }
        }

        $this->assertSame([], $wrong);
    }
}
