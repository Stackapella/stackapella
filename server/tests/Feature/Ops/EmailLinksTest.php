<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Actions\Billing\BillingEvents;
use App\Mail\Account\AlreadyHaveAnAccount;
use App\Mail\Account\ClassCodesReady;
use App\Mail\Account\ConfirmNewEmail;
use App\Mail\Account\EmailChanging;
use App\Mail\Account\NewSignIn;
use App\Mail\Account\NoticeDecided;
use App\Mail\Account\PaymentFailed;
use App\Mail\Account\RecoveredBySupport;
use App\Mail\Account\ResetPassword;
use App\Mail\Account\SignInLinkMail;
use App\Mail\Account\TrialEnding;
use App\Mail\Account\VerifyEmailAddress;
use App\Mail\Support\SupportAnswer;
use App\Models\Notice;
use App\Models\SupportReply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * Every link an account email carries is absolute, on the host its page lives on (the app's, or
 * the public site's for a public page), whichever host the request that sent it came in on, and
 * opens a page there; on a one-host install (self-hosted) every link is on that host. Dates are
 * the person's own.
 */
final class EmailLinksTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, Mailable>
     */
    private static function emails(): array
    {
        $reply = (new SupportReply(['body' => 'Thanks for writing.']))->setRelation('admin', new User(['name' => 'Hillel']));

        return [
            'sign-up confirm' => new VerifyEmailAddress('tok', 'Ana'),
            'sign-in link' => new SignInLinkMail('tok'),
            'already have an account' => new AlreadyHaveAnAccount('tok', 'ana@example.com'),
            'reset' => new ResetPassword('tok', 'ana@example.com'),
            'email changing' => new EmailChanging('ana.new@example.com', 'tok'),
            'confirm new email' => new ConfirmNewEmail('tok'),
            'new sign-in' => new NewSignIn('Safari on an iPhone'),
            'recovered by support' => new RecoveredBySupport('ana.new@example.com'),
            'class codes ready' => new ClassCodesReady('Ana Ruiz'),
            'trial ending' => new TrialEnding('12 November'),
            'payment failed' => new PaymentFailed,
            'notice decided' => new NoticeDecided(Notice::KEPT, 'The links were to a spam site.'),
            'support answer' => new SupportAnswer($reply, 'Ana'),
        ];
    }

    private static function render(Mailable $mail): Email
    {
        config(['mail.default' => 'array']);
        $transport = Mail::mailer('array')->getSymfonyTransport();
        self::assertInstanceOf(ArrayTransport::class, $transport);
        $transport->flush();
        Mail::mailer('array')->to('ana@example.com')->sendNow($mail);
        $email = $transport->messages()->last()?->getOriginalMessage();
        self::assertInstanceOf(Email::class, $email);

        return $email;
    }

    /**
     * @return list<string>
     */
    private static function links(Email $email): array
    {
        preg_match_all('#href="([^"]+)"#', (string) $email->getHtmlBody(), $html);
        preg_match_all('#https?://\S+#', (string) $email->getTextBody(), $text);

        return array_values(array_unique([...array_map('html_entity_decode', $html[1]), ...array_map(static fn (string $u): string => rtrim($u, '.)'), $text[0])]));
    }

    public function test_on_two_hosts_each_link_is_on_its_pages_host_whichever_host_sent_it(): void
    {
        config(['app.url' => 'https://app.stackapella.test', 'hosts.public_url' => 'https://stackapella.test']);
        // Sent while a request came in on the public host: url() would follow it there.
        URL::forceRootUrl('https://stackapella.test');

        foreach (self::emails() as $name => $mail) {
            $email = self::render($mail);
            $links = self::links($email);
            $this->assertNotEmpty($links, $name);
            $this->assertNotSame('', trim((string) $email->getTextBody()), $name.': a plain-text part');
            $this->assertStringNotContainsString('—', (string) $email->getTextBody().$email->getSubject(), $name);

            foreach ($links as $link) {
                $public = $name === 'support answer';
                $this->assertStringStartsWith($public ? 'https://stackapella.test/' : 'https://app.stackapella.test/', $link, "{$name}: {$link}");
                // It opens a page there (the app's shell, or the public page), never a redirect away.
                $this->get($link)->assertOk();
            }
        }
    }

    public function test_on_one_host_every_link_is_absolute_on_it(): void
    {
        config(['app.url' => 'https://sing.example.org', 'hosts.public_url' => '']);

        foreach (self::emails() as $name => $mail) {
            foreach (self::links(self::render($mail)) as $link) {
                $this->assertStringStartsWith('https://sing.example.org/', $link, "{$name}: {$link}");
            }
        }
    }

    public function test_a_date_in_an_email_is_the_persons_own(): void
    {
        Mail::fake();
        $ends = Carbon::parse('2026-11-12 03:00:00', 'UTC')->getTimestamp();

        // Still the 11th in Los Angeles; the 12th where no zone is known (UTC).
        app(BillingEvents::class)->trialEnding(User::factory()->create(['timezone' => 'America/Los_Angeles', 'locale' => 'en']), $ends);
        app(BillingEvents::class)->trialEnding(User::factory()->create(['timezone' => null, 'reminder_timezone' => null, 'locale' => 'en']), $ends);
        app(BillingEvents::class)->trialEnding(User::factory()->create(['timezone' => 'Not/AZone', 'reminder_timezone' => 'Asia/Tokyo', 'locale' => 'en']), $ends);

        $dates = [];
        Mail::assertQueued(TrialEnding::class, function (TrialEnding $mail) use (&$dates): bool {
            $dates[] = $mail->on;

            return true;
        });
        $this->assertSame(['November 11, 2026', 'November 12, 2026', 'November 12, 2026'], $dates);
    }
}
