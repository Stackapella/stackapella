<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Actions\Users\SignInWithProvider;
use App\Models\AuditLog;
use App\Models\EarlyAccessLink;
use App\Models\User;
use App\Support\Accounts\BetaPass;
use App\Support\Accounts\EarlyAccess;
use App\Support\Flags;
use App\Support\Pages\MudeoArrival;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Hillel's shared invite link (docs/decisions.md, "invites"; contract/api.md, "inviteLink.show"):
 * one untracked link that lets anyone holding it make an account while sign-ups are paused, and
 * opens the app; never "from mudeo". Paused or replaced, it lets nobody in.
 */
final class InviteLinkTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $with
     * @return array<string, mixed>
     */
    private function form(string $email, array $with = []): array
    {
        return ['name' => 'Ana Ruiz', 'email' => $email, 'password' => 'a-fine-long-passphrase-42', 'birthMonth' => 5, 'birthYear' => 1990, 'elapsedMs' => 30000, ...$with];
    }

    private function code(EarlyAccessLink $link): string
    {
        return substr(EarlyAccess::url($link), strrpos(EarlyAccess::url($link), '/') + 1);
    }

    public function test_the_admin_has_one_link_to_copy_pause_and_replace(): void
    {
        $admin = $this->actingAsAdmin();

        $shown = $this->getJson('/api/admin/invite');
        $this->assertMatchesContract($shown, 'admin.invite.show', 200);
        $this->assertStringContainsString('/invite/', (string) $shown->json('data.url'));
        $shown->assertJsonPath('data.cap', EarlyAccessLink::PREVIEW_CAP);

        $paused = $this->patchJson('/api/admin/invite', ['paused' => true]);
        $this->assertMatchesContract($paused, 'admin.invite.update', 200);
        $paused->assertJsonPath('data.paused', true);
        $this->assertMatchesContract($this->patchJson('/api/admin/invite', ['paused' => 'maybe']), 'admin.invite.update', 422);

        $replaced = $this->postJson('/api/admin/invite/replace');
        $this->assertMatchesContract($replaced, 'admin.invite.replace', 200);
        $this->assertNotSame($shown->json('data.url'), $replaced->json('data.url'));
        $this->assertTrue(AuditLog::query()->where('action', 'early_access.replaced')->where('actor_id', $admin->id)->exists());

        // The mudeo email's and the preview's links are their own.
        $this->getJson('/api/admin/preview')->assertJsonPath('data.url', fn (string $url): bool => str_contains($url, '/preview/'));
        $this->getJson('/api/admin/early-access')->assertJsonPath('data.url', fn (string $url): bool => str_contains($url, '/early/'));
    }

    public function test_the_link_lets_people_in_while_paused_until_it_is_paused_or_replaced(): void
    {
        Mail::fake();
        app(Flags::class)->set('pause_signups', 'all', null, 'Beta only');
        $link = EarlyAccess::current(EarlyAccessLink::INVITE);
        $code = $this->code($link);

        $open = $this->getJson('/api/invite/'.$code);
        $this->assertMatchesContract($open, 'inviteLink.show', 200);
        $open->assertJsonPath('data.status', 'open');
        // Never mudeo's: its page doesn't mark the visit, and the early link's status doesn't know it.
        $this->assertNull(collect($open->headers->getCookies())->first(fn ($c) => $c->getName() === MudeoArrival::COOKIE));
        $this->getJson('/api/early/'.$code)->assertJsonPath('data.status', 'closed');

        $this->postJson('/api/auth/register', $this->form('ana@example.com'))->assertStatus(423);
        $this->postJson('/api/auth/register', $this->form('ana@example.com', ['early' => $code]))->assertStatus(202);
        $ana = User::query()->where('email', 'ana@example.com')->sole();
        $this->assertSame($link->id, $ana->early_access_link_id);
        // Not counted as joining from mudeo.
        $this->assertSame(0, User::query()->whereIn('early_access_link_id', EarlyAccessLink::query()->where('label', EarlyAccessLink::MUDEO)->pluck('id'))->count());

        // With Google too.
        $sam = app(SignInWithProvider::class)->handle('google', 'g-sam', 'Sam', 'sam@example.com', 'en', null, '203.0.113.20', false, $code);
        $this->assertSame($link->id, $sam->early_access_link_id);

        // Paused: nobody new.
        $link->forceFill(['paused_at' => now()])->save();
        $this->getJson('/api/invite/'.$code)->assertJsonPath('data.status', 'paused');
        $this->postJson('/api/auth/register', $this->form('kim@example.com', ['early' => $code]))->assertStatus(423);

        // Replaced: the old code has ended, the new one works.
        $link->forceFill(['paused_at' => null])->save();
        $fresh = EarlyAccess::replace($this->actingAsAdmin(), null, EarlyAccessLink::INVITE);
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/invite/'.$code)->assertJsonPath('data.status', 'closed');
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.31'])->postJson('/api/auth/register', $this->form('kim@example.com', ['early' => $code]))->assertStatus(423);
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.32'])->postJson('/api/auth/register', $this->form('kim@example.com', ['early' => $this->code($fresh)]))->assertStatus(202);

        // A preview code never lets a sign-up through.
        $preview = $this->code(EarlyAccess::current(EarlyAccessLink::PREVIEW));
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.33'])->postJson('/api/auth/register', $this->form('lee@example.com', ['early' => $preview]))->assertStatus(423);
    }

    public function test_claiming_a_link_keeps_no_raw_address_in_the_cache(): void
    {
        $link = EarlyAccess::current(EarlyAccessLink::INVITE);

        $this->assertNotNull(EarlyAccess::claim($this->code($link), '203.0.113.9'));

        $keys = DB::table('cache')->pluck('key')->implode(' ');
        $this->assertStringContainsString('early-access:', $keys);
        $this->assertStringNotContainsString('203.0.113.9', $keys);
    }

    public function test_its_page_opens_the_app_before_launch(): void
    {
        config(['app.coming_soon' => true]);
        $code = $this->code(EarlyAccess::current(EarlyAccessLink::INVITE));

        $page = $this->get('/invite/'.$code)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertNotNull(collect($page->headers->getCookies())->first(fn ($c) => $c->getName() === BetaPass::COOKIE));
        $this->assertNull(collect($page->headers->getCookies())->first(fn ($c) => $c->getName() === MudeoArrival::COOKIE));

        // A made-up code opens nothing.
        $made = $this->get('/invite/not-a-real-code');
        $this->assertNull(collect($made->headers->getCookies())->first(fn ($c) => $c->getName() === BetaPass::COOKIE));
    }
}
