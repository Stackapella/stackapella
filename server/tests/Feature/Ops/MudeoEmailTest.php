<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Actions\Mudeo\MudeoList;
use App\Actions\Users\SignInWithProvider;
use App\Exceptions\Refusal;
use App\Mail\Mudeo\MudeoInvite;
use App\Models\AuditLog;
use App\Models\EarlyAccessLink;
use App\Models\EmailSuppression;
use App\Models\MudeoBatch;
use App\Models\MudeoContact;
use App\Models\User;
use App\Support\Accounts\EarlyAccess;
use App\Support\Flags;
use App\Support\Pages\MudeoArrival;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Telling mudeo's users (contract/api.md, "The mudeo email (L)"): the early-access link that lets
 * them in while sign-ups are paused, and the list Hillel emails by hand in batches of 50.
 */
final class MudeoEmailTest extends TestCase
{
    use RefreshDatabase;

    private function form(string $email, array $with = []): array
    {
        return ['name' => 'Ana Ruiz', 'email' => $email, 'password' => 'a-fine-long-passphrase-42', 'birthMonth' => 5, 'birthYear' => 1990, 'elapsedMs' => 30000, ...$with];
    }

    private function code(EarlyAccessLink $link): string
    {
        return substr(EarlyAccess::url($link), strrpos(EarlyAccess::url($link), '/') + 1);
    }

    public function test_the_link_lets_people_in_while_sign_ups_are_paused_and_marks_them(): void
    {
        Mail::fake();
        app(Flags::class)->set('pause_signups', 'all', null, 'Beta only');
        $code = $this->code(EarlyAccess::current());

        $this->postJson('/api/auth/register', $this->form('ana@singers.stackapella.com'))->assertStatus(423);
        $this->postJson('/api/auth/register', $this->form('ana@singers.stackapella.com', ['early' => 'wrong-code']))->assertStatus(423);
        $this->postJson('/api/auth/register', $this->form('ana@singers.stackapella.com', ['early' => $code]))->assertStatus(202);

        $ana = User::query()->where('email', 'ana@singers.stackapella.com')->sole();
        $this->assertSame(EarlyAccess::current()->id, $ana->early_access_link_id);
        $this->assertSame(1, EarlyAccess::current()->joined);

        // With Google too.
        $sam = app(SignInWithProvider::class)->handle('google', 'g-sam', 'Sam', 'sam@singers.stackapella.com', 'en', null, '203.0.113.20', false, $code);
        $this->assertSame($ana->early_access_link_id, $sam->early_access_link_id);
        $this->expectException(Refusal::class);
        app(SignInWithProvider::class)->handle('google', 'g-kim', 'Kim', 'kim@singers.stackapella.com', 'en', null, '203.0.113.21');
    }

    public function test_the_page_says_whether_it_lets_people_in_and_the_link_holds_when_forwarded(): void
    {
        Mail::fake();
        app(Flags::class)->set('pause_signups', 'all', null, 'Beta only');
        $link = EarlyAccess::current();
        $code = $this->code($link);

        $open = $this->getJson('/api/early/'.$code);
        $this->assertMatchesContract($open, 'earlyAccess.show', 200);
        $open->assertJsonPath('data.status', 'open');
        // The visit is marked as from mudeo, for the landing's welcome; a closed code marks nothing.
        $this->assertNotNull(collect($open->headers->getCookies())->first(fn ($c) => $c->getName() === MudeoArrival::COOKIE));
        $closed = $this->getJson('/api/early/no-such-code')->assertJsonPath('data.status', 'closed');
        $this->assertNull(collect($closed->headers->getCookies())->first(fn ($c) => $c->getName() === MudeoArrival::COOKIE));

        // Five a day from one address.
        for ($i = 0; $i < EarlyAccess::PER_IP; $i++) {
            $this->assertNotNull(EarlyAccess::claim($code, '203.0.113.30'));
        }
        $this->assertNull(EarlyAccess::claim($code, '203.0.113.30'));
        $this->assertNotNull(EarlyAccess::claim($code, '203.0.113.31'));

        // Full at its cap.
        $link->refresh()->forceFill(['cap' => $link->joined])->save();
        $this->getJson('/api/early/'.$code)->assertJsonPath('data.status', 'full');

        // The page is the app's, kept out of search and with no referrer.
        $page = $this->get('/early/'.$code);
        $page->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_the_admin_pauses_raises_and_replaces_the_link(): void
    {
        $admin = $this->actingAsAdmin();
        $shown = $this->getJson('/api/admin/early-access');
        $this->assertMatchesContract($shown, 'admin.earlyAccess.show', 200);
        $shown->assertJsonPath('data.cap', 500)->assertJsonPath('data.status', 'open');
        $old = $this->code(EarlyAccess::current());

        $paused = $this->patchJson('/api/admin/early-access', ['paused' => true, 'cap' => 800]);
        $this->assertMatchesContract($paused, 'admin.earlyAccess.update', 200);
        $paused->assertJsonPath('data.status', 'paused')->assertJsonPath('data.cap', 800);
        $this->assertMatchesContract($this->patchJson('/api/admin/early-access', ['cap' => 0]), 'admin.earlyAccess.update', 422);
        $this->getJson('/api/early/'.$old)->assertJsonPath('data.status', 'paused');

        $replaced = $this->postJson('/api/admin/early-access/replace');
        $this->assertMatchesContract($replaced, 'admin.earlyAccess.replace', 200);
        $replaced->assertJsonPath('data.cap', 800)->assertJsonPath('data.paused', true)->assertJsonCount(1, 'data.earlier');
        $this->getJson('/api/early/'.$old)->assertJsonPath('data.status', 'closed');
        $this->assertNotSame($old, $this->code(EarlyAccess::current()));
        $this->assertTrue(AuditLog::query()->where('action', 'early_access.replaced')->where('actor_id', $admin->id)->exists());

        // Editors and singers see none of it.
        $this->actingAsEditor();
        $this->getJson('/api/admin/early-access')->assertForbidden();
        $this->getJson('/api/admin/mudeo')->assertForbidden();
    }

    public function test_the_import_cleans_the_list_and_batches_go_most_recently_active_first(): void
    {
        $this->actingAsAdmin();
        EmailSuppression::record('bounced@singers.stackapella.com', 'permanent_bounce');
        User::factory()->create(['email' => 'already@singers.stackapella.com']);
        $csv = "id,name,email,created_at,updated_at\n"
            ."1,Ana,  Ana@Singers.stackapella.com ,2019-01-01 10:00:00,2024-06-01 10:00:00\n"
            ."2,Ana again,ana@singers.stackapella.com,2019-01-01,2024-01-01\n"
            ."3,Not one,not-an-address,2019-01-01,2024-01-01\n"
            ."4,Test,someone@example.com,2019-01-01,2024-01-01\n"
            ."5,Relay,x1y2@privaterelay.appleid.com,2019-01-01,2023-01-01\n"
            ."6,Bounced,bounced@singers.stackapella.com,2019-01-01,2024-01-01\n"
            ."7,Already,already@singers.stackapella.com,2019-01-01,2024-01-01\n"
            ."8,Sam,sam@choir.stackapella.com,2019-01-01,2025-01-01\n";

        $imported = $this->post('/api/admin/mudeo/import', ['file' => UploadedFile::fake()->createWithContent('mudeo-users.csv', $csv)], ['Accept' => 'application/json']);
        $this->assertMatchesContract($imported, 'admin.mudeo.import', 200);
        $imported->assertJsonPath('data.imported', 3)->assertJsonPath('data.skipped', ['invalid' => 1, 'duplicate' => 1, 'test' => 1, 'suppressed' => 1, 'hasAccount' => 1, 'alreadyListed' => 0]);
        $this->post('/api/admin/mudeo/import', ['file' => UploadedFile::fake()->createWithContent('again.csv', $csv)], ['Accept' => 'application/json'])->assertJsonPath('data.imported', 0)->assertJsonPath('data.skipped.alreadyListed', 3);
        $this->assertMatchesContract($this->post('/api/admin/mudeo/import', ['file' => UploadedFile::fake()->createWithContent('bad.csv', "name\nAna\n")], ['Accept' => 'application/json']), 'admin.mudeo.import', 422);

        $next = $this->getJson('/api/admin/mudeo/next');
        $this->assertMatchesContract($next, 'admin.mudeo.next', 200);
        $next->assertJsonPath('data.emails', ['sam@choir.stackapella.com', 'ana@singers.stackapella.com', 'x1y2@privaterelay.appleid.com'])
            ->assertJsonPath('data.line', 'sam@choir.stackapella.com, ana@singers.stackapella.com, x1y2@privaterelay.appleid.com')
            ->assertJsonPath('data.fits', true)->assertJsonPath('data.remaining', 3);

        $sent = $this->postJson('/api/admin/mudeo/batches', ['ids' => $next->json('data.ids')]);
        $this->assertMatchesContract($sent, 'admin.mudeo.batches.create', 201);
        $sent->assertJsonPath('data.sent', 3)->assertJsonPath('data.toSend', 0)->assertJsonPath('data.sentToday', 3);
        $this->assertMatchesContract($this->postJson('/api/admin/mudeo/batches', ['ids' => $next->json('data.ids')]), 'admin.mudeo.batches.create', 422);
        $this->getJson('/api/admin/mudeo/next')->assertJsonPath('data', ['ids' => [], 'emails' => [], 'line' => '', 'fits' => true, 'remaining' => 0]);

        $undone = $this->deleteJson('/api/admin/mudeo/batches/latest');
        $this->assertMatchesContract($undone, 'admin.mudeo.batches.undo', 200);
        $undone->assertJsonPath('data.sent', 0)->assertJsonPath('data.toSend', 3);
        $this->assertMatchesContract($this->deleteJson('/api/admin/mudeo/batches/latest'), 'admin.mudeo.batches.undo', 404);

        $shown = $this->getJson('/api/admin/mudeo');
        $this->assertMatchesContract($shown, 'admin.mudeo.show', 200);
        $shown->assertJsonPath('data.total', 3)->assertJsonPath('data.limit', 200)->assertJsonPath('data.lastBatch', null);
        $this->assertSame(['mudeo.imported', 'mudeo.batch_sent', 'mudeo.batch_undone'], AuditLog::query()->where('action', 'like', 'mudeo.%')->orderBy('id')->pluck('action')->unique()->values()->all());
    }

    public function test_the_daily_cap_holds_and_can_be_changed(): void
    {
        $admin = $this->actingAsAdmin();
        MudeoBatch::factory()->create(['count' => 180, 'sent_at' => now()]);
        MudeoContact::factory()->count(30)->create();

        $next = $this->getJson('/api/admin/mudeo/next')->assertJsonPath('data.fits', false);
        $refused = $this->postJson('/api/admin/mudeo/batches', ['ids' => $next->json('data.ids')]);
        $this->assertMatchesContract($refused, 'admin.mudeo.batches.create', 409);
        $refused->assertJsonPath('code', 'daily_limit')->assertJsonPath('sentToday', 180)->assertJsonPath('limit', 200);

        $raised = $this->patchJson('/api/admin/mudeo', ['dailyCap' => 300]);
        $this->assertMatchesContract($raised, 'admin.mudeo.update', 200);
        $raised->assertJsonPath('data.limit', 300)->assertJsonPath('data.dailyCap', 300)->assertJsonPath('data.warnAt', 240);
        $this->assertMatchesContract($this->patchJson('/api/admin/mudeo', ['dailyCap' => 0]), 'admin.mudeo.update', 422);
        $this->postJson('/api/admin/mudeo/batches', ['ids' => $next->json('data.ids')])->assertCreated();

        // Tomorrow the count starts again.
        $this->travel(1)->days();
        $this->actingAsAdmin($admin);
        $this->getJson('/api/admin/mudeo')->assertJsonPath('data.sentToday', 0);
    }

    public function test_the_app_sends_each_person_their_own_email_and_a_bounce_comes_off_the_list(): void
    {
        config([
            'mail.default' => 'cloudflare',
            'mail.mailers.cloudflare' => ['transport' => 'cloudflare'],
            'services.cloudflare_email' => ['account_id' => 'acc', 'token' => 'tok'],
            'mail.mudeo' => ['address' => 'hillel@mudeo.stackapella.test', 'reply_to' => 'contact@stackapella.test', 'ready' => true, 'postal_address' => '1 Harmony Lane, Springfield'],
        ]);
        Http::fake(['api.cloudflare.com/*' => function ($request) {
            $to = $request['to'][0]['address'] ?? $request['to'][0];

            return Http::response(['success' => true, 'result' => ['permanent_bounces' => $to === 'gone@voices.stackapella.com' ? ['gone@voices.stackapella.com'] : []]]);
        }]);
        $admin = $this->actingAsAdmin();
        $admin->forceFill(['name' => 'Hillel Coren'])->save();
        MudeoContact::factory()->create(['email' => 'ana@singers.stackapella.com', 'name' => 'ana ruiz', 'mudeo_updated_at' => now()->subDay()]);
        MudeoContact::factory()->create(['email' => 'gone@voices.stackapella.com', 'name' => null, 'mudeo_updated_at' => now()->subYear()]);

        $test = $this->postJson('/api/admin/mudeo/test');
        $this->assertMatchesContract($test, 'admin.mudeo.test', 202);
        $test->assertJsonPath('data.sentTo', $admin->email);

        $sent = $this->postJson('/api/admin/mudeo/send');
        $this->assertMatchesContract($sent, 'admin.mudeo.send', 202);
        $shown = $this->getJson('/api/admin/mudeo');
        $shown->assertJsonPath('data.lastBatch.status', 'sent')->assertJsonPath('data.lastBatch.delivered', 1)->assertJsonPath('data.lastBatch.bounced', 1)->assertJsonPath('data.lastBatch.bounceRate', 0.5);
        $this->assertMatchesContract($this->postJson('/api/admin/mudeo/send'), 'admin.mudeo.send', 409);
        $this->assertMatchesContract($this->deleteJson('/api/admin/mudeo/batches/latest'), 'admin.mudeo.batches.undo', 409);

        // A batch still going out: the next waits for it.
        MudeoBatch::factory()->create(['status' => MudeoBatch::SENDING]);
        $busy = $this->postJson('/api/admin/mudeo/send');
        $this->assertMatchesContract($busy, 'admin.mudeo.send', 409);
        $busy->assertJsonPath('code', 'batch_sending');

        // One email each, from the news address, with one-click unsubscribe and no tracking.
        $sends = collect(Http::recorded())->map(fn ($pair) => $pair[0])->filter(fn ($r) => str_contains($r->url(), 'email/sending/send'))->values();
        $this->assertCount(3, $sends);
        $ana = $sends->first(fn ($r) => ($r['to'][0] ?? null) === 'ana@singers.stackapella.com' || ($r['to'][0]['address'] ?? null) === 'ana@singers.stackapella.com');
        $this->assertNotNull($ana);
        $this->assertSame(['address' => 'hillel@mudeo.stackapella.test', 'name' => 'Hillel from Stackapella'], $ana['from']);
        $this->assertSame('contact@stackapella.test', $ana['reply_to']);
        $this->assertSame('mudeo is now Stackapella, and it finally does what mudeo couldn\'t', $ana['subject']);
        $this->assertStringStartsWith('Hi Ana,', trim((string) $ana['text']));
        // Hillel's words in order: the four paragraphs, the six points, the browser line, then the
        // invitation and its link, then the rest. Bold in the HTML, plain in the text.
        $text = (string) $ana['text'];
        $order = ['who made mudeo', 'closed a few months ago', 'audio only', 'from your very first note', '- Find your voice in a minute.', '- Sing with friends.', 'with nothing to install', 'invited in early', 'Try Stackapella: '.EarlyAccess::url(EarlyAccess::current()), 'shape what comes next', 'I read every message.', 'P.S. Your mudeo videos:', 'This is the only email we\'ll send.'];
        $at = array_map(static fn (string $words): int|false => strpos($text, $words), $order);
        $this->assertNotContains(false, $at, 'Every part of the email is in its text.');
        $this->assertSame($at, array_values(collect($at)->sort()->all()), 'The email\'s parts are in Hillel\'s order.');
        $this->assertStringNotContainsString('*', $text);
        $this->assertStringContainsString('Find your voice in a minute.</strong>', (string) $ana['html']);
        $this->assertStringContainsString('free to start</strong>', (string) $ana['html']);
        $this->assertStringContainsString('>Try Stackapella</a>', (string) $ana['html']);
        $this->assertStringContainsString('Sent to ana@singers.stackapella.com because you had a mudeo account.', $text);
        $this->assertStringContainsString(EarlyAccess::url(EarlyAccess::current()), (string) $ana['text']);
        $this->assertStringContainsString('1 Harmony Lane, Springfield', (string) $ana['html']);
        $this->assertStringNotContainsString('<img', (string) $ana['html']);
        $this->assertSame('List-Unsubscribe=One-Click', $ana['headers']['List-Unsubscribe-Post']);
        $this->assertMatchesRegularExpression('#^<https?://[^>]+/unsubscribe/[^>]+signature=[^>]+>$#', $ana['headers']['List-Unsubscribe']);
        $plain = $sends->first(fn ($r) => ($r['to'][0] ?? null) === 'gone@voices.stackapella.com' || ($r['to'][0]['address'] ?? null) === 'gone@voices.stackapella.com');
        $this->assertStringStartsWith('Hi,', trim((string) $plain['text']));

        $this->assertSame('sent', MudeoContact::query()->where('email', 'ana@singers.stackapella.com')->sole()->status());
        $this->assertSame('bounced', MudeoContact::query()->where('email', 'gone@voices.stackapella.com')->sole()->status());
        $this->assertTrue(AuditLog::query()->where('action', 'mudeo.batch_started')->where('actor_id', $admin->id)->exists());
        $this->assertTrue(AuditLog::query()->where('action', 'mudeo.batch_sent')->exists());

        // The link from the email: one button, then suppressed for good.
        $url = trim($ana['headers']['List-Unsubscribe'], '<>');
        $this->get($url)->assertOk()->assertSee('ana@singers.stackapella.com')->assertHeader('X-Robots-Tag', 'noindex');
        $this->post($url, ['List-Unsubscribe' => 'One-Click'])->assertOk()->assertSee('You&#039;re unsubscribed', false);
        $this->assertSame('opted_out', MudeoContact::query()->where('email', 'ana@singers.stackapella.com')->sole()->status());
        $this->assertTrue(EmailSuppression::isSuppressed('ana@singers.stackapella.com'));
        $this->post(str_replace('signature=', 'signature=x', $url))->assertForbidden();
    }

    public function test_sending_waits_for_the_news_address_and_the_postal_address(): void
    {
        $this->actingAsAdmin();
        MudeoContact::factory()->create();
        config(['mail.mudeo' => ['address' => 'hillel@mudeo.stackapella.test', 'reply_to' => 'contact@stackapella.test', 'ready' => false, 'postal_address' => null]]);

        $refused = $this->postJson('/api/admin/mudeo/send');
        $this->assertMatchesContract($refused, 'admin.mudeo.send', 409);
        $refused->assertJsonPath('code', 'news_not_set_up');
        $this->assertMatchesContract($this->postJson('/api/admin/mudeo/test'), 'admin.mudeo.test', 409);
        $this->getJson('/api/admin/mudeo')->assertJsonPath('data.newsReady', false)->assertJsonPath('data.postalAddressSet', false);

        config(['mail.mudeo.ready' => true]);
        $this->postJson('/api/admin/mudeo/send')->assertStatus(409)->assertJsonPath('code', 'postal_address_missing');
        $this->assertSame(0, MudeoBatch::query()->count());
    }

    public function test_opt_outs_and_bounces_leave_the_list_for_good_and_the_list_goes_after_90_days(): void
    {
        $this->actingAsAdmin();
        $ana = MudeoContact::factory()->create(['email' => 'ana@singers.stackapella.com']);
        $sam = MudeoContact::factory()->create(['email' => 'sam@choir.stackapella.com']);
        $kim = MudeoContact::factory()->create(['email' => 'kim@voices.stackapella.com']);

        $marked = $this->postJson('/api/admin/mudeo/mark', ['emails' => ['Ana@Singers.stackapella.com', 'nobody@voices.stackapella.com'], 'as' => 'opted_out']);
        $this->assertMatchesContract($marked, 'admin.mudeo.mark', 200);
        $marked->assertJsonPath('data', ['marked' => 1, 'notFound' => ['nobody@voices.stackapella.com']]);
        $this->postJson('/api/admin/mudeo/mark', ['emails' => ['sam@choir.stackapella.com'], 'as' => 'bounced'])->assertOk();
        $this->assertMatchesContract($this->postJson('/api/admin/mudeo/mark', ['emails' => ['x@y.com'], 'as' => 'maybe']), 'admin.mudeo.mark', 422);
        $this->assertTrue(EmailSuppression::isSuppressed('ana@singers.stackapella.com'));
        $this->assertTrue(EmailSuppression::isSuppressed('sam@choir.stackapella.com'));
        $this->getJson('/api/admin/mudeo/next')->assertJsonPath('data.emails', ['kim@voices.stackapella.com']);

        $found = $this->getJson('/api/admin/mudeo/contacts?q=singers');
        $this->assertMatchesContract($found, 'admin.mudeo.contacts', 200);
        $found->assertJsonPath('data.0.status', 'opted_out');
        $this->assertMatchesContract($this->getJson('/api/admin/mudeo/contacts?q=a'), 'admin.mudeo.contacts', 422);

        $this->postJson('/api/admin/mudeo/batches', ['ids' => [$kim->sqid()]])->assertCreated();
        $this->travel(89)->days();
        $this->artisan('app:mudeo:prune')->expectsOutputToContain('Deleted 0')->assertSuccessful();
        $this->travel(2)->days();
        $this->artisan('app:mudeo:prune')->expectsOutputToContain('Deleted 2')->assertSuccessful();
        // Kim was mailed: only a hash of the address is left, which keeps her off a re-import
        // and lets her old unsubscribe link still work.
        $this->assertSame(['ana@singers.stackapella.com', MudeoList::hashed('kim@voices.stackapella.com')], MudeoContact::query()->orderBy('id')->pluck('email')->all());
        $this->assertNull($ana->refresh()->name);
        $this->assertNull($sam->fresh());
        $this->assertSame(0, app(MudeoList::class)->prune());
        $this->assertSame([], app(MudeoList::class)->search('hmac'));

        $this->post(MudeoList::unsubscribeUrl($kim->refresh()))->assertOk()->assertDontSee('kim@voices.stackapella.com');
        $this->assertSame('opted_out', $kim->refresh()->status());
        $this->assertFalse(EmailSuppression::isSuppressed('kim@voices.stackapella.com'));
        $csv = storage_path('framework/testing/mudeo-again-'.uniqid().'.csv');
        file_put_contents($csv, "id,name,email,created_at,updated_at\n9,Kim,kim@voices.stackapella.com,2020-01-01,2024-01-01\n");
        $again = app(MudeoList::class)->import($csv, User::factory()->create(), null);
        $this->assertSame([0, 1], [$again['imported'], $again['skipped']['alreadyListed']]);
    }

    public function test_each_email_is_recorded_as_it_goes_so_a_retry_never_mails_anyone_twice(): void
    {
        config(['mail.mudeo' => ['address' => 'hillel@mudeo.stackapella.test', 'reply_to' => 'contact@stackapella.test', 'ready' => true, 'postal_address' => '1 Harmony Lane']]);
        Mail::fake();
        $admin = $this->actingAsAdmin();
        $first = MudeoContact::factory()->create(['email' => 'ana@singers.stackapella.com', 'mudeo_updated_at' => now()->subDays(1)]);
        $quit = MudeoContact::factory()->create(['email' => 'sam@choir.stackapella.com', 'mudeo_updated_at' => now()->subDays(2)]);
        $gone = MudeoContact::factory()->create(['email' => 'kim@voices.stackapella.com', 'mudeo_updated_at' => now()->subDays(3)]);
        $last = MudeoContact::factory()->create(['email' => 'lee@voices.stackapella.com', 'mudeo_updated_at' => now()->subDays(4)]);
        $list = app(MudeoList::class);

        Queue::fake();
        $batch = $list->start($admin, null);
        // Since the batch was picked, Sam opted out elsewhere and Kim's address bounced elsewhere.
        EmailSuppression::record('sam@choir.stackapella.com', 'mudeo_opt_out');
        EmailSuppression::record('kim@voices.stackapella.com', 'permanent_bounce');
        // Ana's email went before the worker died: the retry skips her.
        $first->forceFill(['sent_at' => now()])->save();
        $batch->forceFill(['delivered' => 1])->save();

        $list->deliver($batch->refresh());

        Mail::assertSentCount(1);
        Mail::assertSent(MudeoInvite::class, fn (MudeoInvite $mail): bool => $mail->hasTo('lee@voices.stackapella.com'));
        $this->assertSame(['opted_out', 'bounced', 'sent'], [$quit->refresh()->status(), $gone->refresh()->status(), $last->refresh()->status()]);
        // Neither earlier suppression counts as this batch's bounce.
        $this->assertSame([2, 0, 'sent'], [$batch->refresh()->delivered, $batch->bounced, $batch->status]);
    }

    public function test_those_not_reached_in_time_wait_for_a_later_batch(): void
    {
        config(['mail.mudeo' => ['address' => 'hillel@mudeo.stackapella.test', 'reply_to' => 'contact@stackapella.test', 'ready' => true, 'postal_address' => '1 Harmony Lane']]);
        Mail::fake();
        Queue::fake();
        $admin = $this->actingAsAdmin();
        MudeoContact::factory()->count(2)->create();
        $list = app(MudeoList::class);
        $batch = $list->start($admin, null);

        $list->deliver($batch, 0);

        Mail::assertNothingSent();
        $this->assertSame(0, MudeoContact::query()->whereNotNull('mudeo_batch_id')->count());
        $this->getJson('/api/admin/mudeo/next')->assertJsonCount(2, 'data.emails');
    }

    public function test_the_emails_results_are_daily_totals_never_tied_to_anyone(): void
    {
        config(['counts.enabled' => true]);
        $code = $this->code(EarlyAccess::current());
        $this->withCredentials();

        // The link opened in two browsers, and one of them again in the same visit: two.
        $this->getJson('/api/early/'.$code)->assertOk();
        $this->withUnencryptedCookie(MudeoArrival::COOKIE, '1')->getJson('/api/early/'.$code)->assertOk();
        $this->newVisit();
        $this->getJson('/api/early/'.$code)->assertOk();

        // Someone from it makes a first song: counted once a visit, whatever comes next.
        $this->newVisit();
        $first = $this->withUnencryptedCookie(MudeoArrival::COOKIE, '1')->postJson('/api/counts', ['events' => [['kind' => 'song_created']], 'signedIn' => false])->assertNoContent();
        $this->assertNotNull(collect($first->headers->getCookies())->first(fn ($c) => $c->getName() === MudeoArrival::USED_COOKIE));
        $this->withUnencryptedCookie(MudeoArrival::USED_COOKIE, '1')->postJson('/api/counts', ['events' => [['kind' => 'lesson_done']], 'signedIn' => false])->assertNoContent();
        // Not from mudeo: not counted for it.
        $this->newVisit();
        $this->postJson('/api/counts', ['events' => [['kind' => 'song_created']], 'signedIn' => false])->assertNoContent();

        // A visitor can't send the server's own kinds.
        $this->postJson('/api/counts', ['events' => [['kind' => 'mudeo_visit', 'n' => 20]], 'signedIn' => false])->assertNoContent();

        $this->actingAsAdmin();
        $shown = $this->getJson('/api/admin/mudeo');
        $this->assertMatchesContract($shown, 'admin.mudeo.show', 200);
        $today = collect($shown->json('data.days'))->firstWhere('date', now()->toDateString());
        $this->assertSame(['visits' => 2, 'used' => 1], ['visits' => $today['visits'], 'used' => $today['used']]);
    }

    public function test_the_email_goes_from_stackapella_com_and_is_ready_unless_held_back(): void
    {
        $mudeo = function (?string $ready): array {
            $ready === null ? putenv('MAIL_MUDEO_READY') : putenv('MAIL_MUDEO_READY='.$ready);
            $_ENV['MAIL_MUDEO_READY'] = $_SERVER['MAIL_MUDEO_READY'] = $ready;
            putenv('MAIL_MUDEO_FROM');
            unset($_ENV['MAIL_MUDEO_FROM'], $_SERVER['MAIL_MUDEO_FROM']);

            try {
                return (require config_path('mail.php'))['mudeo'];
            } finally {
                putenv('MAIL_MUDEO_READY');
                unset($_ENV['MAIL_MUDEO_READY'], $_SERVER['MAIL_MUDEO_READY']);
            }
        };

        $this->assertSame(['hillel@stackapella.com', 'contact@stackapella.com', true], [$mudeo(null)['address'], $mudeo(null)['reply_to'], $mudeo(null)['ready']]);
        $this->assertTrue($mudeo('')['ready']);
        $this->assertFalse($mudeo('false')['ready']);
        $this->assertStringNotContainsString('mudeo.stackapella', (string) file_get_contents(config_path('mail.php')));
    }

    /**
     * Another browser: none of the last one's cookies.
     */
    private function newVisit(): void
    {
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];
    }
}
