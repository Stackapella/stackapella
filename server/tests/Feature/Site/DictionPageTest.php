<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Site\DictionPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/**
 * The Diction for singers page (Hillel's design, 2026-11-18): Amazing Grace's line is its template's, stressed on each
 * bar's first beat with the light "the"; the archive's pull quotes are each year's own cited words; the questions say
 * every course is free and link the courses that sing Italian and church Latin; the Spanish page is in Spanish.
 */
final class DictionPageTest extends TestCase
{
    // The page requests write rate-limit rows: in a transaction, so none outlives the test.
    use RefreshDatabase;

    public function test_amazing_graces_line_is_stressed_on_the_bars_first_beats(): void
    {
        $line = DictionPage::grace();

        $this->assertSame(['A', 'maz', 'ing', 'grace,', 'how', 'sweet', 'the', 'sound'], array_column($line, 'word'));
        $this->assertSame(['maz', 'grace,', 'sweet', 'sound'], array_values(array_column(array_filter($line, static fn (array $s): bool => $s['stressed']), 'word')));
        $this->assertSame(['the'], array_values(array_column(array_filter($line, static fn (array $s): bool => $s['light']), 'word')));
        $this->assertSame('/əˈmeɪ.zɪŋ ˈɡɹeɪs haʊ ˈswiːt ðə ˈsaʊnd/', DictionPage::ipa($line));
    }

    public function test_the_page_shows_its_sounds_demos_and_every_cited_fact(): void
    {
        $html = (string) $this->get('/courses/diction')->assertOk()->getContent();

        // The headline's sung vowels, their sounds, and the line of Amazing Grace from the template.
        $this->assertStringContainsString('<span class="cp-dn-v">E</span>ver<span class="cp-dn-v">y</span>', $html);
        $this->assertStringContainsString('/ˈɛv.ɹi ˈwɜːd ˈhɜːd/', $html);
        $this->assertStringContainsString('/əˈmeɪ.zɪŋ ˈɡɹeɪs haʊ ˈswiːt ðə ˈsaʊnd/', $html);
        $this->assertStringContainsString('/js/public/diction.js', $html);
        $this->assertSame(2, substr_count($html, 'data-di-track="'));

        // Each year's pull quote sits in its row with the row's own source.
        $love = (array) Lang::get('site.courses.pages.diction.love');
        $keys = array_keys($love['sources']);
        foreach ($love['archive']['rows'] as [$year, , , $quote, , $cites]) {
            $n = array_search($cites[0], $keys, true) + 1;
            $this->assertMatchesRegularExpression('#“'.preg_quote(e($quote), '#').'”<sup class="s-cite"><a href="\#src-'.$n.'"#u', $html, "{$year}'s quote is cited");
        }

        // Every course is free, and Latin and Italian lead to the courses that sing them.
        $this->assertStringContainsString('every course is free', $html);
        $this->assertStringContainsString('href="/courses/classical-singing-basics"', $html);
        $this->assertStringContainsString('href="/courses/other-languages"', $html);
        $this->assertCount(10, $love['sources']);
    }

    public function test_the_spanish_page_sings_its_own_headline(): void
    {
        $html = (string) $this->get('/es/courses/diction')->assertOk()->getContent();

        $this->assertStringContainsString('C<span class="cp-dn-v">a</span>d<span class="cp-dn-v">a</span>', $html);
        $this->assertStringContainsString('/ˈka.da pa.ˈla.bɾa ˈkla.ɾa/', $html);
        $this->assertStringContainsString('De Trento a la <span class="cp-dn-it">galería del fondo</span>', $html);
        // The English example's sounds stay off the Spanish page's labels.
        $this->assertStringNotContainsString('/ɪn pleɪn wɜːdz/', $html);
    }
}
