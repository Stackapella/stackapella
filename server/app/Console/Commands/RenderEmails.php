<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\Account\AccountMail;
use App\Mail\Account\AlreadyHaveAnAccount;
use App\Mail\Account\ConfirmNewEmail;
use App\Mail\Account\EmailChanging;
use App\Mail\Account\ExportReady;
use App\Mail\Account\NewSignIn;
use App\Mail\Account\NoticeDecided;
use App\Mail\Account\NoticeIssued;
use App\Mail\Account\PaymentFailed;
use App\Mail\Account\RecoveredBySupport;
use App\Mail\Account\ResetPassword;
use App\Mail\Account\SignInLinkMail;
use App\Mail\Account\TrialEnding;
use App\Mail\Account\VerifyEmailAddress;
use App\Mail\Ideas\IdeaVoicesMail;
use App\Mail\Mudeo\MudeoInvite;
use App\Mail\Support\SupportAnswer;
use App\Models\Notice;
use App\Models\SupportReply;
use App\Models\User;
use App\Notifications\Admin\AdminSignedIn;
use App\Notifications\Admin\OpsAlert;
use App\Notifications\Admin\SecondFactorAdded;
use App\Notifications\Admin\SecondFactorLocked;
use App\Notifications\Admin\SecondFactorReset;
use App\Support\Mail\EmailSkin;
use Illuminate\Console\Command;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification as Notifications;
use Symfony\Component\Mime\Email;

/**
 * Every email the app sends, rendered through the real templates with made-up people, for a
 * look before a change ships: an .html and a .txt per email and an index.md with each one's
 * subject, sender and headers. An account email is rendered in both skins (EmailSkin): serious as
 * <name>.html and game as <name>.game.html. Nothing is sent: everything goes through the array
 * mailer, and the command refuses to run otherwise. `server/tool/email_shots.py <dir>` then photographs each
 * one on a desktop and a phone, light and dark.
 */
final class RenderEmails extends Command
{
    /** The skin the account emails wear in this pass. */
    private string $skin = EmailSkin::SERIOUS;

    protected $signature = 'app:emails:render {dir : Where to write them (made if missing)} {--accent= : An accent the account emails wear (coral, violet, ... or #rrggbb), else each skin\'s own}';

    protected $description = 'Render every email to files, with made-up people, sending nothing';

    public function handle(): int
    {
        config(['mail.default' => 'array', 'queue.default' => 'sync']);
        $dir = rtrim((string) $this->argument('dir'), '/');

        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            $this->error("Can't make {$dir}.");

            return self::FAILURE;
        }

        $index = ["# Every email Stackapella sends\n", 'Rendered by `php artisan app:emails:render` through the real templates with made-up people (nothing was sent: the array mailer only). `python3 tool/email_shots.py` adds a desktop (680 px) and a phone (390 px) picture of each, light and dark.'."\n"];
        $n = 0;

        foreach ($this->emails() as [$name, $heading, $who, $make]) {
            $this->skin = EmailSkin::SERIOUS;
            $email = $this->capture($make);
            $this->skin = EmailSkin::GAME;
            $game = $this->capture($make);
            $file = sprintf('%02d-%s', $n++, $name);
            file_put_contents("{$dir}/{$file}.html", (string) $email->getHtmlBody());
            file_put_contents("{$dir}/{$file}.txt", (string) $email->getTextBody());
            // Only an account email has a game skin (always dark: its "light" pictures are how a
            // mail app in light mode shows it); the others render the same either way.
            $skinned = $game->getHtmlBody() !== $email->getHtmlBody();

            if ($skinned) {
                file_put_contents("{$dir}/{$file}.game.html", (string) $game->getHtmlBody());
            }

            $index[] = sprintf('## %02d. %s', $n - 1, $heading)."\n";
            $index[] = '- **Subject:** '.$email->getSubject();
            $index[] = '- **To:** '.$who;
            $index[] = '- **From:** '.implode(', ', array_map(static fn ($a): string => $a->toString(), $email->getFrom()));

            foreach (['Reply-To', 'List-Unsubscribe', 'List-Unsubscribe-Post'] as $header) {
                if (($value = $email->getHeaders()->get($header)) !== null) {
                    $index[] = '- **'.$header.':** `'.$value->getBodyAsString().'`';
                }
            }

            $index[] = "- **Files:** [{$file}.html]({$file}.html), [{$file}.txt]({$file}.txt)".($skinned ? '' : "\n");

            if ($skinned) {
                $index[] = '- **Game skin:** '.$game->getSubject();
                $index[] = "- **Files:** [{$file}.game.html]({$file}.game.html)\n";
            }

            $index[] = "```text\n".trim((string) $email->getTextBody())."\n```\n";
        }

        file_put_contents("{$dir}/index.md", implode("\n", $index));
        $this->info("Rendered {$n} emails to {$dir}.");

        return self::SUCCESS;
    }

    /**
     * Send one through the array mailer, and take it back off.
     *
     * @param  callable(): void  $make
     */
    private function capture(callable $make): Email
    {
        $transport = Mail::mailer('array')->getSymfonyTransport();

        if (! $transport instanceof ArrayTransport) {
            throw new \LogicException('Refusing to render: the mailer isn\'t the array mailer.');
        }

        $transport->flush();
        $make();
        $sent = $transport->messages()->last();
        $email = $sent?->getOriginalMessage();

        return $email instanceof Email ? $email : throw new \LogicException('Nothing was rendered.');
    }

    private function mail(Mailable $mail, string $to = 'ana@example.com'): void
    {
        if ($mail instanceof AccountMail) {
            $accent = $this->option('accent');
            $mail->skinned($this->skin, is_string($accent) && $accent !== '' ? (str_starts_with($accent, '#') ? ['accent' => 'custom', 'customAccent' => $accent] : ['accent' => $accent]) : null);
        }

        Mail::mailer('array')->to($to)->sendNow($mail);
    }

    private function notify(Notification $notification): void
    {
        Notifications::sendNow(new User(['name' => 'Hillel', 'email' => 'admin@example.com']), $notification);
    }

    /**
     * Each email: its file name, the index's heading, who gets it, and how it's made.
     *
     * @return list<array{0: string, 1: string, 2: string, 3: callable(): void}>
     */
    private function emails(): array
    {
        $admin = new User(['name' => 'Hillel', 'email' => 'admin@example.com']);
        $browser = 'Safari on an iPhone';
        $reply = (new SupportReply(['body' => "Thanks for writing. When the app can't hear you, it's usually the browser's permission.\n\nIn the app, open the mic picker on the recording screen and choose your mic."]))->setRelation('admin', $admin);
        $digest = [
            __('ops.digest.yesterday', ['signups' => 14, 'plus' => 2, 'shared' => 9]),
            __('ops.digest.waiting.support', ['count' => 1]),
            __('ops.digest.waiting.reports', ['count' => 2]),
            __('ops.digest.check', ['status' => 'WARN', 'label' => 'Turnstile', 'message' => 'No Turnstile keys: shield mode can\'t ask for a challenge when sign-ups or reports surge.']),
        ];

        return [
            ['mudeo-new', 'The mudeo email (sent once, by Hillel, in batches)', 'A mudeo singer on the list', fn () => $this->mail(new MudeoInvite('Ana', 'https://stackapella.com/early/ABCD1234', 'https://stackapella.com/mudeo/unsubscribe/abc?signature=def'))],
            ['mudeo-no-name', 'The same, without a first name', 'A mudeo singer on the list', fn () => $this->mail(new MudeoInvite(null, 'https://stackapella.com/early/ABCD1234', 'https://stackapella.com/mudeo/unsubscribe/abc?signature=def'))],
            ['verify', 'After signing up with a password (and when asked again)', 'The person signing up', fn () => $this->mail(new VerifyEmailAddress('token', 'Ana'))],
            ['verify-no-name', 'The same, when no name was given', 'The person signing up', fn () => $this->mail(new VerifyEmailAddress('token', ''))],
            ['sign-in-link', 'Signing in by email', 'Whoever asks to sign in by email', fn () => $this->mail(new SignInLinkMail('token'))],
            ['already-have-account', 'Someone signs up with an address that already has an account', 'The address', fn () => $this->mail(new AlreadyHaveAnAccount('token', 'ana@example.com'))],
            ['reset-password', 'Forgot password', 'Whoever asks', fn () => $this->mail(new ResetPassword('token', 'ana@example.com'))],
            ['email-changing', 'Changing their email: to the old address, with an undo', 'The old address', fn () => $this->mail(new EmailChanging('ana.new@example.com', 'token'))],
            ['confirm-new-email', 'Changing their email: to the new address', 'The new address', fn () => $this->mail(new ConfirmNewEmail('token'), 'ana.new@example.com')],
            ['new-sign-in', 'A sign-in on a new browser', 'The account', fn () => $this->mail(new NewSignIn($browser))],
            ['new-sign-in-app', 'A sign-in on a new app device', 'The account', fn () => $this->mail(new NewSignIn('the Stackapella app on an iPhone', true))],
            ['recovered-by-support', 'Support moved the account to a new address', 'The old address', fn () => $this->mail(new RecoveredBySupport('ana.new@example.com'))],
            ['export-ready', 'Download my data is ready', 'Whoever asked', fn () => $this->mail(new ExportReady('https://stackapella.com/account/export/abc', '3 November'))],
            ['trial-ending', 'Three days before a Plus trial ends', 'A Plus subscriber on a trial', fn () => $this->mail(new TrialEnding('1 November'))],
            ['payment-failed', 'A Plus renewal payment failed', 'A Plus subscriber', fn () => $this->mail(new PaymentFailed)],
            ['notice-suspended', 'A suspended account\'s statement of reasons', 'A suspended person', fn () => $this->mail(new NoticeIssued(__('notices.kinds.account_suspended'), __('notices.rules.terms'), 'https://app.stackapella.com/notice?token=abc', 'Links to a spam site in every song title.'))],
            ['notice-decided', 'The answer to a review they asked for', 'Whoever asked for the review', fn () => $this->mail(new NoticeDecided(Notice::KEPT, 'The links were to a spam site.'))],
            ['idea-voices', 'New voices on their idea (at most daily per idea)', 'The idea\'s writer', fn () => $this->mail(new IdeaVoicesMail('Ana', 'Sunday morning', [['name' => 'Ben', 'part' => 'Alto']], false, 'https://stackapella.com/ideas/abc', 'https://stackapella.com/unsubscribe/abc'))],
            ['idea-complete', 'Every part of their idea is sung', 'The idea\'s writer', fn () => $this->mail(new IdeaVoicesMail('Ana', 'Sunday morning', [['name' => 'Ben', 'part' => 'Alto'], ['name' => 'Chen', 'part' => 'Tenor']], true, 'https://stackapella.com/ideas/abc', 'https://stackapella.com/unsubscribe/abc'))],
            ['support-answer', 'A reply to a support message', 'Whoever wrote to support', fn () => $this->mail(new SupportAnswer($reply, 'Ana'))],
            ['support-answer-no-account', 'The same, to someone without an account', 'Whoever wrote to support', fn () => $this->mail(new SupportAnswer($reply, null))],
            ['admin-signed-in', 'An admin signs in', 'The admins', fn () => $this->notify(new AdminSignedIn($admin, 'google', 'Mozilla/5.0 (Macintosh) Safari/605.1.15'))],
            ['second-factor-added', 'An admin adds a second factor', 'The admins', fn () => $this->notify(new SecondFactorAdded($admin, 'passkey', 'Mozilla/5.0 (Macintosh) Safari/605.1.15'))],
            ['second-factor-locked', 'Too many wrong second-factor codes', 'The admins', fn () => $this->notify(new SecondFactorLocked($admin))],
            ['second-factor-reset', 'A second factor was reset', 'The admins', fn () => $this->notify(new SecondFactorReset($admin))],
            ['ops-alert', 'Hourly, when something is over its line', 'The admins', fn () => $this->notify(new OpsAlert(__('ops.alerts.subject', ['count' => 1]), __('ops.alerts.title'), ['The queue has 340 jobs waiting (its line is 200).']))],
            ['ops-digest', 'Every morning', 'The admins', fn () => $this->notify(new OpsAlert(__('ops.digest.subject', ['date' => '27 Oct']), __('ops.digest.title'), $digest))],
            ['ops-digest-quiet', 'Every morning, a quiet day', 'The admins', fn () => $this->notify(new OpsAlert(__('ops.digest.subject', ['date' => '27 Oct']), __('ops.digest.title'), [__('ops.digest.yesterday', ['signups' => 0, 'plus' => 0, 'shared' => 0]), __('ops.digest.all_clear')]))],
            ['ops-readiness', 'When a launch readiness check changes', 'The admins', fn () => $this->notify(new OpsAlert(__('ops.readiness.subject', ['count' => 1]), __('ops.readiness.title'), ['FAIL Backups: the last backup is 30 hours old.']))],
        ];
    }
}
