<?php

declare(strict_types=1);

namespace Tests\Unit\Site;

use App\Support\Site\SiteCss;
use Tests\TestCase;

/**
 * A page family's stylesheet (docs/css-split-plan.md, step 4): the whole sheet less the rules none of whose selectors could
 * match with the classes the family can show, in the sheet's own order and each in its @media, so a page looks as with
 * the whole sheet.
 */
final class SiteCssTest extends TestCase
{
    public function test_a_selector_needs_the_classes_outside_not_is_where_and_attribute_selectors(): void
    {
        $this->assertSame(['s-a', 's-b'], SiteCss::required('.s-a .s-b>p'));
        $this->assertSame(['s-a'], SiteCss::required('.s-a:not(.s-calm) :is(.s-x,.s-y) :where(.s-z)'));
        $this->assertSame(['s-a'], SiteCss::required('.s-a[href$=".pdf"]'));
        $this->assertSame(['s-a', 's-b'], SiteCss::required('.s-a:has(.s-b)'));
        $this->assertSame([], SiteCss::required(':root[data-skin=\'game\'] body'));
        // Tailwind's escapes: a variant's colon, a fraction, an arbitrary value, a leading digit.
        $this->assertSame(['md:flex'], SiteCss::required('.md\\:flex'));
        $this->assertSame(['w-1/2'], SiteCss::required('.w-1\\/2'));
        $this->assertSame(['bg-[#fff]'], SiteCss::required('.bg-\\[\\#fff\\]'));
        $this->assertSame(['2xl:p-0'], SiteCss::required('.\\32 xl\\:p-0'));
        $this->assertSame(['hover:s-a'], SiteCss::required('.hover\\:s-a:hover'));
    }

    public function test_the_sheet_keeps_what_could_match_in_its_order_and_its_media(): void
    {
        $css = '@layer theme,base,utilities;@layer theme{:root{--a:1}}'
            .'.s-a{color:red}.s-b{color:blue}.s-a .s-b{color:green}.s-b,.s-a{margin:0}'
            .'body{margin:0}.s-a:not(.s-c){gap:1px}'
            .'@media (min-width:900px){.s-b{gap:2px}}@media (min-width:900px){.s-a{gap:3px}.s-b{gap:4px}}'
            .'@keyframes s-b-spin{to{rotate:1turn}}@font-face{font-family:x;src:url(x.woff2)}'
            .'@layer utilities{.flex{display:flex}.grid{display:grid}}'
            .'.s-a[data-x="a,b"]{color:pink}';
        $can = static fn (string $class): bool => in_array($class, ['s-a', 'flex'], true);

        $this->assertSame(
            '@layer theme,base,utilities;@layer theme{:root{--a:1}}'
            .'.s-a{color:red}.s-b,.s-a{margin:0}'
            .'body{margin:0}.s-a:not(.s-c){gap:1px}'
            .'@media (min-width:900px){.s-a{gap:3px}}'
            .'@keyframes s-b-spin{to{rotate:1turn}}@font-face{font-family:x;src:url(x.woff2)}'
            .'@layer utilities{.flex{display:flex}}'
            .'.s-a[data-x="a,b"]{color:pink}',
            SiteCss::cut($css, $can),
        );
        // An empty @layer still says where its layer goes.
        $this->assertSame('@layer utilities{}', SiteCss::cut('@layer utilities{.grid{display:grid}}', $can));
    }

    public function test_a_page_family_by_its_path_in_any_language(): void
    {
        $this->assertSame('landing', SiteCss::family('/'));
        $this->assertSame('landing', SiteCss::family('/es'));
        $this->assertSame('songs', SiteCss::family('/songs'));
        $this->assertSame('songs', SiteCss::family('/es/songs/amazing-grace/alto'));
        $this->assertSame('songs', SiteCss::family('/songbook'));
        $this->assertSame('tools', SiteCss::family('/tools/tuner'));
        $this->assertSame('tools', SiteCss::family('/warmups/lip-trills'));
        $this->assertSame('reference', SiteCss::family('/voice-types/alto'));
        $this->assertSame('reference', SiteCss::family('/es/intervals/perfect-fifth'));
        $this->assertSame('courses', SiteCss::family('/courses/vibrato'));
        $this->assertSame('info', SiteCss::family('/pricing'));
        $this->assertSame('info', SiteCss::family('/for/organizations'));
        $this->assertSame('info', SiteCss::family('/es/pricing'));
    }

    public function test_the_classes_a_page_shows_in_either_quoting(): void
    {
        $shown = SiteCss::shown('<p class="s-a  flex"><i class=\'s-b s-a\'></i><b class="x&amp;y"></b><b data-class="nope"></b>');
        sort($shown);
        $this->assertSame(['flex', 's-a', 's-b', 'x&y'], $shown);
    }
}
