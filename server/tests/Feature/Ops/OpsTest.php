<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Actions\Templates\SyncOfficialTemplates;
use App\Console\Commands\BackupDrill;
use App\Console\Commands\BackupRun;
use App\Enums\SecurityEventType;
use App\Models\EmailSuppression;
use App\Models\SecurityEvent;
use App\Models\Take;
use App\Models\User;
use App\Notifications\Admin\OpsAlert;
use App\Services\EventSchedule\Signature;
use App\Support\Backups\DatabaseDump;
use App\Support\Errors\ScrubEvent;
use App\Support\Ops\Readiness;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Sentry\Event;
use Sentry\ExceptionDataBag;
use Sentry\Frame;
use Sentry\Stacktrace;
use Sentry\UserDataBag;
use Tests\TestCase;

/**
 * Running Stackapella (docs/backend-plan.md A3, L): readiness, the heartbeat, alerts, the digest,
 * backups and the restore drill, Sentry's scrubbing and tunnel, the mail webhook, security.txt
 * and the launch domain's redirects.
 */
final class OpsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        config(['admin.admin_google_ids' => ['g-hillel']]);

        return User::factory()->admin()->create(['google_id' => 'g-hillel', 'email' => 'hillel@example.com']);
    }

    public function test_readiness_names_each_problem_with_its_fix_and_emails_only_what_changed(): void
    {
        Notification::fake();
        $admin = $this->admin();

        $this->artisan('app:readiness --json')->assertFailed();
        // On the schedule, the email is the alarm: the job itself succeeds.
        $this->artisan('app:readiness --alert')->assertSuccessful();
        Notification::assertSentTo($admin, OpsAlert::class, fn (OpsAlert $alert): bool => collect($alert->lines)->contains(fn (string $line): bool => str_contains($line, 'Scheduler went from new to fail')));

        // Nothing changed: no email. The scheduler beats: one email, saying so.
        Notification::fake();
        $this->artisan('app:readiness --alert');
        Notification::assertNothingSent();
        $this->artisan('app:heartbeat')->assertSuccessful();
        $this->artisan('app:readiness --alert');
        Notification::assertSentTo($admin, OpsAlert::class, fn (OpsAlert $alert): bool => collect($alert->lines)->contains(fn (string $line): bool => str_contains($line, 'Scheduler went from fail to pass')));
        $this->artisan('app:doctor')->expectsOutputToContain('Environment');
    }

    public function test_alerts_go_once_past_their_line_and_the_digest_says_whats_waiting(): void
    {
        Notification::fake();
        $admin = $this->admin();
        config(['ops.alerts.sign_ups_an_hour' => 2]);

        foreach ([1, 2, 3] as $i) {
            SecurityEvent::factory()->create(['type' => SecurityEventType::SignUp]);
        }

        $this->artisan('app:alerts')->assertSuccessful();
        $this->artisan('app:alerts')->assertSuccessful();
        Notification::assertSentToTimes($admin, OpsAlert::class, 1);

        $this->artisan('app:digest --print')->expectsOutputToContain('Yesterday: 3 sign-ups')->assertSuccessful();
        $this->artisan('app:digest')->assertSuccessful();
        Notification::assertSentToTimes($admin, OpsAlert::class, 2);
    }

    public function test_a_backup_is_kept_pruned_and_restored_in_the_drill(): void
    {
        if (! DatabaseDump::toolsInstalled()) {
            $this->markTestSkipped('mysqldump isn\'t installed here (MYSQL_CLIENT_PATH).');
        }

        // Made inside the test's transaction, so the dump (its own connection) can't see it, as a
        // row made just before a real backup may not be in it. A second on, the backup's name is
        // later than the row: the drill must still pass (it once failed whenever the clock ticked
        // over here).
        User::factory()->create();
        $this->travel(1)->seconds();
        Storage::disk('backup')->put('db/daily/2000-01-01-000000.sql.gz', 'old');

        foreach (range(1, 30) as $day) {
            Storage::disk('backup')->put(sprintf('db/daily/2001-01-%02d-000000.sql.gz', $day), 'old');
        }

        $this->artisan('app:backup:run')->assertSuccessful();
        $files = Storage::disk('backup')->files('db/daily');
        $this->assertCount(30, $files);
        $this->assertNotContains('db/daily/2000-01-01-000000.sql.gz', $files);
        $this->assertNotEmpty(Storage::disk('backup')->files('db/monthly'));

        $this->artisan('app:backup:drill')->expectsOutputToContain('foreign keys')->assertSuccessful();
        $this->assertNotNull((new \ReflectionMethod(Readiness::class, 'time'))->invoke(app(Readiness::class), Readiness::DRILL));

        // Never into the live database, nor one that doesn't say it's for the drill.
        config(['database.restore_drill_database' => strtoupper((string) DB::connection()->getDatabaseName())]);
        $this->artisan('app:backup:drill')->expectsOutputToContain('is the live database')->assertFailed();
        config(['database.restore_drill_database' => 'stackapella_reports']);
        $this->artisan('app:backup:drill')->expectsOutputToContain('must have')->assertFailed();
        $this->assertSame('fail', collect(app(Readiness::class)->run())->firstWhere('key', 'drill_database')?->status);
        $this->assertTrue(User::query()->exists());
        config(['database.restore_drill_database' => '']);
    }

    public function test_the_drill_finds_rows_a_backup_lost_and_keys_that_point_at_nothing(): void
    {
        $live = DB::connection()->getDatabaseName();
        $scratch = $live.'_drill_check';
        // The scratch tables are made on a second connection: DDL on this one would commit the
        // test's transaction. The database goes once the transaction has rolled back.
        config(['database.connections.drill_check' => config('database.connections.'.config('database.default'))]);
        $ddl = DB::connection('drill_check');
        $ddl->statement("drop database if exists `{$scratch}`");
        $ddl->statement("create database `{$scratch}`");
        $this->beforeApplicationDestroyed(fn () => DB::connection('drill_check')->statement("drop database if exists `{$scratch}`"));

        foreach (['users', 'takes', 'parts'] as $table) {
            $ddl->statement("create table `{$scratch}`.`{$table}` like `{$live}`.`{$table}`");
        }

        // A copy without the middle user: the backup "lost" it. Without the newest instead,
        // nothing is lost (it may simply be newer than the backup).
        $users = User::factory()->count(3)->create(['created_at' => now()->subDays(2)]);
        DB::statement("insert into `{$scratch}`.`users` select * from `users` where id <> ?", [$users[1]->id]);
        $lost = new \ReflectionMethod(BackupDrill::class, 'lost');
        $this->assertSame(1, $lost->invoke(null, 'users', $scratch, now()));
        DB::statement("delete from `{$scratch}`.`users` where id = ?", [$users[2]->id]);
        DB::statement("insert into `{$scratch}`.`users` select * from `users` where id = ?", [$users[1]->id]);
        $this->assertSame(0, $lost->invoke(null, 'users', $scratch, now()));

        // A take whose part isn't in the copy points at nothing.
        $take = Take::factory()->create();
        DB::statement("insert into `{$scratch}`.`takes` select * from `takes` where id = ?", [$take->id]);
        [$keys, $problems] = (new \ReflectionMethod(BackupDrill::class, 'dangling'))->invoke(null, $live, $scratch);
        $this->assertGreaterThan(0, $keys);
        $this->assertContains('1 takes.part_id point at no parts', $problems);
    }

    public function test_readiness_fails_in_production_when_cookies_could_travel_over_http(): void
    {
        $secure = fn (): ?string => collect(app(Readiness::class)->run())->firstWhere('key', 'secure-cookies')?->status;
        config(['session.secure' => false]);
        $this->assertSame('pass', $secure());

        $this->app['env'] = 'production';
        $this->assertSame('fail', $secure());
        config(['session.secure' => true]);
        $this->assertSame('pass', $secure());
    }

    public function test_only_the_jobs_listed_get_a_sentry_cron_monitor(): void
    {
        $this->assertSame(['backup-database'], config('sentry.cron_monitors'));
        $before = new \ReflectionProperty(ScheduledEvent::class, 'beforeCallbacks');
        $monitored = collect(app(Schedule::class)->events())->filter(fn (ScheduledEvent $event): bool => $before->getValue($event) !== [])->map(fn (ScheduledEvent $event): ?string => $event->description)->values()->all();
        $this->assertSame(['backup-database'], $monitored);
    }

    public function test_readiness_fails_production_on_the_public_link_alphabet_and_warns_without_turnstile(): void
    {
        $status = fn (string $key): ?string => collect(app(Readiness::class)->run())->firstWhere('key', $key)?->status;
        config(['app.sqids_alphabet_set' => false, 'services.turnstile.site_key' => null, 'services.turnstile.secret' => null]);
        $this->assertSame('pass', $status('sqids'));
        $this->assertSame('pass', $status('turnstile'));

        $this->app['env'] = 'production';
        $this->assertSame('fail', $status('sqids'));
        $this->assertSame('warn', $status('turnstile'));
        config(['app.sqids_alphabet_set' => true, 'services.turnstile.site_key' => 'site', 'services.turnstile.secret' => 'secret']);
        $this->assertSame('pass', $status('sqids'));
        $this->assertSame('pass', $status('turnstile'));
    }

    public function test_a_self_hosted_install_is_checked_only_for_what_it_needs(): void
    {
        $this->app['env'] = 'production';
        $checks = fn (): Collection => collect(app(Readiness::class)->run())->keyBy('key');
        config(['mail.default' => 'smtp']);
        $this->assertSame('fail', $checks()->get('transport')?->status);
        $this->assertTrue($checks()->has('stripe'));

        config(['selfhost.enabled' => true]);
        $mine = $checks();
        // Any real mailer, and none of Stackapella's own services or docs.
        $this->assertSame('pass', $mine->get('transport')?->status);
        foreach (['stripe', 'stripe_webhook', 'connect_webhook', 'invoice_ninja', 'social', 'bounces', 'event_schedule'] as $ours) {
            $this->assertFalse($mine->has($ours), $ours);
        }
        $this->assertStringNotContainsString('docs/', $mine->pluck('fix')->implode(' '));
        $this->assertStringNotContainsString('Hillel', $mine->pluck('fix')->implode(' '));
        config(['mail.default' => 'log']);
        $this->assertSame('fail', $checks()->get('transport')?->status);
    }

    public function test_readiness_says_where_the_songs_come_from_when_the_library_is_empty(): void
    {
        $songs = fn (): ?string => collect(app(Readiness::class)->run())->firstWhere('key', 'songs')?->status;
        $this->assertSame('fail', $songs());
        $this->assertStringContainsString('content.sh fetch', (string) collect(app(Readiness::class)->run())->firstWhere('key', 'songs')?->fix);

        app(SyncOfficialTemplates::class)->handle();
        $this->assertSame('pass', $songs());
    }

    public function test_the_file_backup_copies_new_files_and_lets_deleted_ones_go_after_30_days(): void
    {
        Storage::fake('backup');
        Storage::fake('private');
        Storage::fake('media');
        $backup = Storage::disk('backup');

        // A new file is copied once; an export never is.
        Storage::disk('private')->put('takes/abc.flac', 'take');
        Storage::disk('private')->put('exports/data.zip', 'zip');
        $this->artisan('app:backup:run --files')->expectsOutputToContain('Copied 1')->assertSuccessful();
        $this->artisan('app:backup:run --files')->expectsOutputToContain('Copied 0')->assertSuccessful();
        $this->assertFalse($backup->exists('private/exports/data.zip'));

        // An export copied before the rule goes at once.
        $backup->put('private/exports/old.zip', 'zip');
        // A deleted take's copy stays 30 days, then goes.
        Storage::disk('private')->delete('takes/abc.flac');
        $this->artisan('app:backup:run --files')->expectsOutputToContain('removed 1')->assertSuccessful();
        $this->assertFalse($backup->exists('private/exports/old.zip'));
        $this->assertTrue($backup->exists('private/takes/abc.flac'));

        $this->travel(29)->days();
        $this->artisan('app:backup:run --files')->assertSuccessful();
        $this->assertTrue($backup->exists('private/takes/abc.flac'));
        $this->travel(1)->days();
        $this->artisan('app:backup:run --files')->expectsOutputToContain('removed 1')->assertSuccessful();
        $this->assertFalse($backup->exists('private/takes/abc.flac'));
        $this->assertSame('[]', $backup->get(BackupRun::MISSING));
    }

    public function test_expired_cache_rows_are_pruned_and_live_ones_kept(): void
    {
        config(['cache.default' => 'database']);
        DB::table('cache')->insert([
            ['key' => 'limiter:ip:203.0.113.9', 'value' => 'i:1;', 'expiration' => now()->subMinute()->getTimestamp()],
            ['key' => 'limiter:ip:203.0.113.10', 'value' => 'i:1;', 'expiration' => now()->addHour()->getTimestamp()],
        ]);
        DB::table('cache_locks')->insert(['key' => 'upload:x', 'owner' => 'o', 'expiration' => now()->subMinute()->getTimestamp()]);

        $this->artisan('app:cache:prune')->expectsOutputToContain('Deleted 2 expired')->assertSuccessful();
        $this->assertSame(['limiter:ip:203.0.113.10'], DB::table('cache')->pluck('key')->all());
        $this->assertSame(0, DB::table('cache_locks')->count());
    }

    public function test_sentry_sees_no_person_and_the_tunnel_forwards_only_our_dsn(): void
    {
        $event = Event::createEvent();
        $event->setRequest(['method' => 'POST', 'url' => 'https://stackapella.test/api/x?token=abc', 'data' => ['password' => 'p'], 'cookies' => ['s' => 'x'], 'headers' => ['User-Agent' => 'UA', 'Cookie' => 'c']]);
        $event->setUser(UserDataBag::createFromUserIdentifier('42')->setEmail('ana@example.com')->setIpAddress('203.0.113.9'));
        $event->setMessage('Failed for ana@example.com from 203.0.113.9 with token=abcdef');
        $clean = ScrubEvent::event($event);

        $this->assertSame(['method' => 'POST', 'url' => 'https://stackapella.test/api/x', 'headers' => ['User-Agent' => 'UA']], $clean?->getRequest());
        $this->assertSame('42', $clean?->getUser()?->getId());
        $this->assertNull($clean?->getUser()?->getEmail());
        $this->assertSame('Failed for [email] from [ip] with token=[secret]', $clean?->getMessage());

        $frame = new Frame('handle', 'app/Actions/Support/SupportInbox.php', 40, null, null, ['email' => 'ana@example.com']);
        $thrown = Event::createEvent();
        $thrown->setRequest(['url' => 'https://stackapella.test/api/notices/'.str_repeat('a1', 20)]);
        $thrown->setExceptions([new ExceptionDataBag(new \RuntimeException('x'), new Stacktrace([$frame]))]);
        $scrubbed = ScrubEvent::event($thrown);
        $this->assertSame([], $scrubbed?->getExceptions()[0]->getStacktrace()?->getFrames()[0]->getVars());
        $this->assertSame('https://stackapella.test/api/notices/[token]', $scrubbed?->getRequest()['url']);

        Http::fake();
        config(['sentry.browser_dsn' => 'https://key@o1.ingest.sentry.io/123']);
        $this->call('POST', '/log/errors', [], [], [], ['CONTENT_TYPE' => 'text/plain'], json_encode(['dsn' => 'https://other@evil.example/1'])."\n{}")->assertStatus(400);
        $this->call('POST', '/log/errors', [], [], [], ['CONTENT_TYPE' => 'text/plain'], json_encode(['dsn' => 'https://key@o1.ingest.sentry.io/123'])."\n{}")->assertOk();
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://o1.ingest.sentry.io/api/123/envelope/');
        Http::assertSentCount(1);
    }

    public function test_bounces_and_complaints_from_our_worker_are_suppressed_and_only_when_signed(): void
    {
        config(['services.cloudflare_email.webhook_secret' => 'mail-secret']);
        $body = (string) json_encode(['events' => [['type' => 'bounce', 'bounceType' => 'hard', 'recipient' => 'Gone@Example.com'], ['type' => 'bounce', 'bounceType' => 'soft', 'recipient' => 'busy@example.com'], ['type' => 'complaint', 'recipient' => 'angry@example.com']]]);
        $send = fn (string $signature) => $this->call('POST', '/webhooks/mail', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STACKAPELLA_SIGNATURE' => $signature], $body);

        $send(Signature::make('wrong', $body, now()->getTimestamp()))->assertStatus(400);
        $send(Signature::make('mail-secret', $body, now()->getTimestamp()))->assertOk();

        $this->assertTrue(EmailSuppression::isSuppressed('gone@example.com'));
        $this->assertTrue(EmailSuppression::isSuppressed('angry@example.com'));
        $this->assertFalse(EmailSuppression::isSuppressed('busy@example.com'));
    }

    public function test_security_txt_and_the_launch_domains_other_names(): void
    {
        $txt = $this->get('/.well-known/security.txt');
        $txt->assertOk()->assertHeader('Content-Type', 'text/plain; charset=utf-8');
        $this->assertStringContainsString('Contact: mailto:contact@stackapella.com', (string) $txt->getContent());
        $this->assertStringContainsString('Canonical: https://stackapella.test/.well-known/security.txt', (string) $txt->getContent());

        // With two hosts, each answers it itself (RFC 9116 scopes it to its host), naming both.
        config(['hosts.public_url' => 'https://site.stackapella.test']);
        $onApp = $this->get('https://stackapella.test/.well-known/security.txt')->assertOk();
        $this->assertStringContainsString("Canonical: https://site.stackapella.test/.well-known/security.txt\nCanonical: https://stackapella.test/.well-known/security.txt\n", (string) $onApp->getContent());
        $this->get('https://site.stackapella.test/.well-known/security.txt')->assertOk();
        config(['hosts.public_url' => null]);

        config(['ops.redirect_hosts' => ['www.stackapella.test']]);
        $this->get('http://www.stackapella.test/h/abc?ref=x')->assertStatus(301)->assertRedirect('https://stackapella.test/h/abc?ref=x');
        $this->post('http://www.stackapella.test/api/auth/sign-in')->assertStatus(308);
        $this->get('https://stackapella.test/up')->assertSuccessful();

        // Before launch: temporary, never cached, to the public site.
        config(['ops.redirect_temporary' => true, 'hosts.public_url' => 'https://beta.stackapella.test']);
        $temporary = $this->get('http://www.stackapella.test/pricing');
        $temporary->assertStatus(302)->assertRedirect('https://beta.stackapella.test/pricing');
        $this->assertStringContainsString('no-store', (string) $temporary->headers->get('Cache-Control'));
        $this->post('http://www.stackapella.test/api/reports')->assertStatus(307);
        // A host that isn't listed is never sent anywhere.
        $this->get('https://unknown.example.test/up')->assertSuccessful();
    }
}
