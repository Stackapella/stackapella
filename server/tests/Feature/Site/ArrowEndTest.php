<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Flags;
use App\Support\Site\Icons;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Support\BetaFixtures;
use Tests\TestCase;

/**
 * A link's arrow stays with its last word (Hillel: "Fix the arrow, it should be after singers not next line"): the
 * words stay one translated string, and the last word and the arrow share one no-wrap span (x-site.arrow-end).
 */
final class ArrowEndTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    public function test_the_last_word_and_the_icon_share_one_no_wrap_span(): void
    {
        $arrow = (string) Icons::svg('arrow', 16);

        $this->assertSame('<span>Learning tracks for your <span class="s-nw">singers'.$arrow.'</span></span>', (string) Icons::after('Learning tracks for your singers'));
        $this->assertSame('<span><span class="s-nw">Open '.$arrow.'</span></span>', (string) Icons::after(' Open ', spaced: true));
        // The words are escaped, never markup.
        $this->assertSame('<span>Tom &amp; <span class="s-nw">&lt;b&gt;'.$arrow.'</span></span>', (string) Icons::after('Tom & <b>'));
    }

    public function test_the_collection_pages_for_choirs_link_keeps_singers_and_its_arrow_together_in_english_and_spanish(): void
    {
        config(['app.is_nexus' => true, 'app.coming_soon' => false]);
        $this->catalogue();
        app(Flags::class)->set('lang_es', 'on', null, 'Spanish checked');

        foreach (['/songs/hymns' => 'Directing a choir? Learning tracks for your singers', '/es/songs/hymns' => '¿Diriges un coro? Pistas de estudio para tus cantantes'] as $path => $words) {
            $html = (string) $this->get($path)->assertOk()->getContent();
            $last = (string) strrchr($words, ' ');
            $head = substr($words, 0, -strlen($last));

            $this->assertStringContainsString(
                'ref=for-choirs-collection" class="s-lk"><span>'.e($head).' <span class="s-nw">'.e(ltrim($last)).'<svg ',
                $html,
                $path,
            );
            $this->assertStringNotContainsString('&#8288;', $html, $path);
        }
    }

    public function test_no_view_puts_an_arrow_after_a_word_joiner_or_a_bare_space_in_a_text_link(): void
    {
        foreach (File::allFiles(resource_path('views')) as $file) {
            $view = $file->getContents();
            $this->assertStringNotContainsString('&#8288;<x-site.icon name="arrow"', $view, $file->getRelativePathname());
            $this->assertDoesNotMatchRegularExpression('#class="s-lk[^"]*">\{\{[^<]*\}\} <x-site\.icon name="arrow"#', $view, $file->getRelativePathname());
        }
    }
}
