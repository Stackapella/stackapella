<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Flags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * "Teaming up is Plus" on the site (docs/pricing-plan.md; lang/en/teams.php): while the plus_teams switch is off every
 * page says what it says today, and on, each place that called friends or groups free says that whoever starts one needs
 * Plus and everyone invited joins free.
 */
final class PlusTeamsCopyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.is_nexus' => true, 'app.coming_soon' => false]);
    }

    /** @return array<string, array{string, string, string}> path, today's words, the words with the switch on */
    public static function pages(): array
    {
        return [
            'the plan cards' => ['/pricing', 'Sing with friends, and choir groups', 'Join any song or choir group you&#039;re invited to'],
            'pricing\'s choir question' => ['/pricing', 'Nothing for you or your singers: choir groups are free.', 'Nothing for your singers: they join free. Starting the group takes Plus.'],
            'pricing\'s table' => ['/pricing', 'Friends singing on one of your songs', 'Inviting friends to sing on your songs'],
            'the landing\'s question' => ['/', 'and singing with friends. You can start without an account.', 'lets you invite friends to sing and start choir groups'],
            'help on groups' => ['/help/groups', 'Groups are free.', 'Starting a group takes Plus; every singer joins free.'],
            'the choirs feature' => ['/features/choirs', 'Choir groups are free, for the director and every member.', 'Starting a choir group takes Plus; every member joins free.'],
            'for choir directors' => ['/for/choir-directors', 'your singers never pay: learning on Stackapella is free.', 'they join your group free. Starting the group takes Plus.'],
            'a compare page' => ['/compare/howli', 'singing with friends and choir groups. Plus,', 'lets you invite friends to sing and start choir groups'],
        ];
    }

    #[DataProvider('pages')]
    public function test_a_page_says_teaming_up_is_plus_only_once_the_switch_is_on(string $path, string $today, string $on): void
    {
        $off = (string) $this->get($path)->assertOk()->getContent();
        $this->assertStringContainsString($today, $off, $path.': today\'s words while the switch is off');
        $this->assertStringNotContainsString($on, $off);

        app(Flags::class)->set('plus_teams', 'on', null, 'test');
        $html = (string) $this->get($path)->assertOk()->getContent();
        $this->assertStringContainsString($on, $html, $path.': the new words with the switch on');
        $this->assertStringNotContainsString($today, $html);
    }

    public function test_plus_lists_singing_together_once_the_switch_is_on(): void
    {
        $line = 'Invite friends to sing on your songs and start choir groups. Everyone you invite joins free.';
        $this->assertStringNotContainsString($line, (string) $this->get('/pricing')->getContent());
        app(Flags::class)->set('plus_teams', 'on', null, 'test');
        $html = (string) $this->get('/pricing')->assertOk()->getContent();
        $this->assertStringContainsString($line, $html);
        $this->assertStringContainsString('Private arrangements.', $html, 'the other gains stay');
    }

    public function test_the_spanish_pages_say_it_in_spanish(): void
    {
        app(Flags::class)->set('lang_es', 'on', null, 'test');
        app(Flags::class)->set('plus_teams', 'on', null, 'test');
        $html = (string) $this->get('/es/pricing')->assertOk()->getContent();
        $this->assertStringContainsString('Únete a cualquier canción o grupo de coro al que te inviten', $html);
        $this->assertStringNotContainsString('Join any song or choir group', $html);
    }

    public function test_every_change_names_words_that_exist(): void
    {
        foreach (array_keys((array) __('teams')) as $key) {
            $this->assertTrue(Lang::has($key), $key.' changes words that no longer exist');
        }
    }
}
