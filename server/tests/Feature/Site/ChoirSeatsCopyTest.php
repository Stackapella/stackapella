<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Flags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Choir seats on the site (docs/choir-seats-plan.md; lang/en/seats.php): while the choir_seats switch is off every page
 * says what it says today, and on, no page promises that a choir's members join free: each singer needs a seat.
 */
final class ChoirSeatsCopyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.is_nexus' => true, 'app.coming_soon' => false]);
    }

    /** @return array<string, array{string, string}> path, the words with the switch on */
    public static function pages(): array
    {
        return [
            'pricing\'s choir question' => ['/pricing', 'Each singer in a choir group needs a seat. The director buys them for the choir, from 10 seats'],
            'pricing\'s table' => ['/pricing', 'A seat from your director'],
            'help on groups' => ['/help/groups', 'Every singer needs a seat: the director buys them for the choir'],
            'the choirs feature' => ['/features/choirs', 'Every singer in a choir group has a seat, which the director buys for the choir'],
            'for choir directors' => ['/for/choir-directors', 'Each singer in your group needs a seat, which you buy for the choir from 10 seats'],
            'a compare page' => ['/compare/howli', 'is your seat in any choir group'],
            'the landing' => ['/', 'Every singer in a choir group has a seat, bought for the choir by its director'],
        ];
    }

    #[DataProvider('pages')]
    public function test_a_page_says_each_singer_needs_a_seat_only_once_the_switch_is_on(string $path, string $on): void
    {
        $off = (string) $this->get($path)->assertOk()->getContent();
        $this->assertStringNotContainsString($on, $off, $path.': today\'s words while the switch is off');

        // Over teaming up's words too: no "join free" for a choir's members.
        app(Flags::class)->set('plus_teams', 'on', null, 'test');
        app(Flags::class)->set('choir_seats', 'on', null, 'test');
        // /pricing bolds each answer's short answer: read the words without that markup.
        $html = str_replace(['<b>', '</b>'], '', (string) $this->get($path)->assertOk()->getContent());
        $this->assertStringContainsString($on, $html, $path.': the seat words with the switch on');
        $this->assertStringNotContainsString('your singers join free', $html);
        $this->assertStringNotContainsString('every member joins free', $html);
        $this->assertStringNotContainsString('every singer joins free', $html);
    }

    public function test_the_spanish_pages_say_it_in_spanish(): void
    {
        app(Flags::class)->set('lang_es', 'on', null, 'test');
        app(Flags::class)->set('choir_seats', 'on', null, 'test');
        $html = (string) $this->get('/es/pricing')->assertOk()->getContent();
        $this->assertStringContainsString('Cada cantante de un grupo de coro necesita una plaza.', $html);
        $this->assertStringNotContainsString('Each singer in a choir group needs a seat', $html);
    }

    public function test_every_change_names_words_that_exist(): void
    {
        foreach (array_keys((array) __('seats')) as $key) {
            $this->assertTrue(Lang::has($key), $key.' changes words that no longer exist');
        }
    }
}
