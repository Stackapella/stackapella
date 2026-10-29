<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Models\User;
use App\Support\Usage\Plans;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Running Stackapella yourself (docs/open-source-plan.md, 5.5; config/selfhost.php): every account
 * has Plus, the server calls out to no one on its own, the legal pages are the operator's, the
 * addresses are settings, and it all runs without the content folders. stackapella.com, with none
 * of it set, is unchanged.
 */
final class SelfHostTest extends TestCase
{
    use RefreshDatabase;

    private string $scratch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scratch = sys_get_temp_dir().'/selfhost-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($this->scratch);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->scratch);
        parent::tearDown();
    }

    public function test_on_a_self_hosted_install_every_account_has_plus(): void
    {
        $ada = User::factory()->create()->fresh() ?? throw new \LogicException;

        $this->assertSame('free', app(Plans::class)->plan($ada), 'stackapella.com: Plus is bought');
        $hosted = $this->getJson('/api/auth/config')->assertOk()->assertJsonPath('data.selfHosted', false);
        $this->assertMatchesContract($hosted, 'auth.config', 200);

        config(['selfhost.enabled' => true]);
        $this->assertSame('plus', app(Plans::class)->plan($ada));
        $this->actingAs($ada)->getJson('/api/account')->assertOk()->assertJsonPath('data.plan', 'plus');
        $this->getJson('/api/auth/config')->assertOk()->assertJsonPath('data.selfHosted', true);
    }

    public function test_the_cloudflare_check_runs_only_behind_cloudflare(): void
    {
        $check = collect(app(Schedule::class)->events())->first(static fn (Event $event): bool => $event->description === 'check-cloudflare-ranges');
        $this->assertNotNull($check);

        config(['selfhost.behind_cloudflare' => true]);
        $this->assertTrue($check->filtersPass($this->app), 'stackapella.com: checked weekly');

        config(['selfhost.behind_cloudflare' => false]);
        $this->assertFalse($check->filtersPass($this->app));

        Http::fake();
        $this->artisan('app:check-cloudflare-ranges')->expectsOutputToContain('nothing to check')->assertSuccessful();
        Http::assertNothingSent();
    }

    public function test_a_self_hosted_install_shows_its_own_legal_texts_never_ours(): void
    {
        $ours = $this->get('/legal/terms')->assertOk();
        $ours->assertSee('Terms of use');
        $ours->assertDontSee("isn't written yet");

        // Until the operator writes them: a page saying so, under the same title.
        config(['selfhost.enabled' => true, 'selfhost.legal_path' => $this->scratch]);
        $this->get('/legal/terms')->assertOk()->assertSee('Terms of use')->assertSee("isn't written yet")->assertDontSee('The short version');
        $this->get('/legal/privacy')->assertOk()->assertSee("isn't written yet");

        File::ensureDirectoryExists($this->scratch.'/en');
        File::put($this->scratch.'/en/terms.md', "---\ntitle: Riverside Choir's terms\nversion: 3\nreviewed: true\n---\n\n# Riverside Choir's terms\n\nBe kind to each other.\n");
        $this->get('/legal/terms')->assertOk()->assertSee("Riverside Choir's terms")->assertSee('Be kind to each other.')->assertDontSee("isn't written yet");
    }

    public function test_our_addresses_are_settings(): void
    {
        $this->get('/')->assertOk()->assertSee('contact@stackapella.com')->assertSee('bsky.app/profile/stackapella.com');

        config(['site.contact_email' => 'singers@riverside.example.org', 'site.social' => ['x' => '', 'bluesky' => '']]);
        $page = (string) $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('singers@riverside.example.org', $page);
        $this->assertStringNotContainsString('bsky.app', $page);
    }

    public function test_it_runs_without_the_content_folders(): void
    {
        $empty = $this->scratch.'/empty';
        File::ensureDirectoryExists($empty);
        config([
            'templates.folders' => [$empty],
            'content.guides' => $empty,
            'content.whats_new' => $empty.'/none.json',
        ]);

        $this->artisan('app:templates:sync')->assertSuccessful();
        $this->getJson('/api/templates')->assertOk()->assertJsonCount(0, 'data');
        $this->get('/songs')->assertOk();
        $this->get('/guides')->assertOk()->assertDontSee('href="/guides/', false);
        $this->get('/guides/what-is-harmony')->assertNotFound();
        $this->get('/whats-new')->assertOk();
        $this->get('/')->assertOk();
        $this->assertStringNotContainsString('/guides/', (string) $this->get('/sitemap.xml')->assertOk()->getContent());

        // The demo seed skips what needs a song.
        $this->seed(DemoSeeder::class);
        $this->assertTrue(User::query()->where('email', 'ada@example.com')->exists());
    }
}
