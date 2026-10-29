<?php

declare(strict_types=1);

namespace Tests\Feature\Accounts;

use App\Mail\Account\SignInLinkMail;
use App\Mail\Account\TrialEnding;
use App\Models\User;
use App\Support\Mail\EmailSkin;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Account emails wear the recipient's skin (Hillel: "style user emails depending on the game mode
 * settings"): game until they choose serious, serious for an address with no account, and the
 * plain text the same either way.
 */
final class EmailSkinTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_skin_follows_the_account_and_is_serious_for_anyone_else(): void
    {
        // Queued encrypted: the links in them sign in, reset or confirm.
        $this->assertInstanceOf(ShouldBeEncrypted::class, new SignInLinkMail('token'));

        User::factory()->create(['email' => 'ana@example.com', 'appearance' => null]);
        User::factory()->create(['email' => 'ben@example.com', 'appearance' => [...User::APPEARANCE_DEFAULT, 'skin' => 'serious']]);

        $this->assertSame(EmailSkin::GAME, EmailSkin::forAddress('ana@example.com'));
        $this->assertSame(EmailSkin::GAME, EmailSkin::forAddress(' Ana@Example.com '));
        $this->assertSame(EmailSkin::SERIOUS, EmailSkin::forAddress('ben@example.com'));
        $this->assertSame(EmailSkin::SERIOUS, EmailSkin::forAddress('nobody@example.com'));
        $this->assertSame(EmailSkin::SERIOUS, EmailSkin::forAddress(null));
    }

    public function test_the_button_and_links_take_the_accent_tuned_to_each_card_and_always_read_at_aa(): void
    {
        User::factory()->create(['email' => 'ana@example.com', 'appearance' => [...User::APPEARANCE_DEFAULT, 'accent' => 'violet']]);

        $this->assertStringContainsString('#b296ff', (new SignInLinkMail('token'))->to('ana@example.com')->render());
        $this->assertStringContainsString('#7853c5', (new SignInLinkMail('token'))->skinned(EmailSkin::SERIOUS)->to('ana@example.com')->render());
        // No account, or no accent: the skin's own.
        $this->assertStringContainsString('#0e7571', (new SignInLinkMail('token'))->to('nobody@example.com')->render());

        $appearances = [null, ['accent' => 'amber'], ['accent' => 'custom', 'customAccent' => '#e6e600'], ['accent' => 'custom', 'customAccent' => '#101010'], ['accent' => 'custom', 'customAccent' => 'nope']];
        foreach ([EmailSkin::GAME, EmailSkin::SERIOUS] as $skin) {
            foreach ($appearances as $appearance) {
                $p = EmailSkin::palette($skin, $appearance);
                $this->assertGreaterThanOrEqual(4.5, EmailSkin::contrast($p['button'], $p['buttonText']), $skin.' '.json_encode($appearance));
                $this->assertGreaterThanOrEqual(4.5, EmailSkin::contrast($p['link'], $p['card']), $skin.' '.json_encode($appearance));
            }
        }
    }

    public function test_a_game_email_has_the_cabinet_and_its_words_and_the_text_stays_plain(): void
    {
        User::factory()->create(['email' => 'ana@example.com', 'appearance' => null]);

        $game = (new SignInLinkMail('token'))->to('ana@example.com');
        $html = $game->render();
        $this->assertStringContainsString('#12101e', $html);
        $this->assertStringContainsString('Continue your game', $html);
        $this->assertStringContainsString('Press start', $html);
        $this->assertStringContainsString('Player 1', $html);
        // No web font: nothing is fetched when it opens.
        $this->assertStringNotContainsString('fonts.googleapis', $html);
        $this->assertStringNotContainsString('@font-face', $html);

        $serious = (new SignInLinkMail('token'))->to('nobody@example.com');
        $plain = $serious->render();
        $this->assertStringNotContainsString('#12101e', $plain);
        $this->assertStringContainsString('Your sign-in link', $plain);
        $this->assertStringNotContainsString('Player 1', $plain);

        // A plain email's words stay its own in the game skin, bold included in the skin's colour.
        $trial = (new TrialEnding('1 November'))->to('ana@example.com')->render();
        $this->assertStringContainsString('Your trial ends soon', $trial);
        $this->assertStringContainsString('color:#f7f4ff;">1 November</strong>', $trial);
    }
}
