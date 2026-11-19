<?php

declare(strict_types=1);

namespace Tests\Unit\Pages;

use App\Support\Pages\BeltingPage;
use PHPUnit\Framework\TestCase;

/**
 * The belting course page's headline and headings (App\Support\Pages\BeltingPage): the headline steps down at its comma
 * in either language, and a heading's phrase is set in italics only where the words have it, always escaped.
 */
final class BeltingPageTest extends TestCase
{
    public function test_the_headline_steps_down_at_its_comma(): void
    {
        $this->assertSame(['Bright,', 'not loud.'], BeltingPage::steps('Bright, not loud.'));
        $this->assertSame(['Brillante,', 'no fuerte.'], BeltingPage::steps('Brillante, no fuerte.'));
        $this->assertSame(['One line'], BeltingPage::steps('One line'));
    }

    public function test_a_phrase_is_set_in_italics_where_the_words_have_it(): void
    {
        $this->assertSame('Say it, <em>then sing it</em>', (string) BeltingPage::em('Say it, then sing it', 'then sing it'));
        $this->assertSame('From a spoken <em>&quot;hey&quot;</em> to a call', (string) BeltingPage::em('From a spoken "hey" to a call', '"hey"'));
        $this->assertSame('from <em>the street</em> to <em>the deck</em>', (string) BeltingPage::em('from the street to the deck', ['the street', 'the deck']));
        $this->assertSame('Dilo y luego cántalo', (string) BeltingPage::em('Dilo y luego cántalo', 'then sing it'));
        $this->assertSame('&lt;b&gt; <em>&lt;i&gt;</em>', (string) BeltingPage::em('<b> <i>', '<i>'));
    }

    public function test_the_drawings_scales(): void
    {
        $this->assertSame([34, 204], [BeltingPage::semitoneY(3), BeltingPage::semitoneY(-2)]);
        $this->assertSame([66, 291], [BeltingPage::beatX(0), BeltingPage::beatX(2.5)]);
        $this->assertSame([120, 600], [BeltingPage::hzX(1000), BeltingPage::hzX(5000)]);
    }
}
