<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Mail\Account\GrowthViewerInvite;
use App\Models\AuditLog;
use App\Models\GrowthViewer;
use App\Models\User;
use App\Support\Admin\AdminGate;
use App\Support\Admin\GrowthViewers;
use App\Support\Admin\SecondFactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * A growth viewer (Hillel, 2026-11-23): an advisor who reads Admin's anonymous growth numbers and nothing else, signed
 * in as strongly as an admin, granted and taken away from a console.
 */
final class GrowthViewerTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'advisor@example.com';

    private function viewer(array $user = [], array $grant = []): User
    {
        $person = User::factory()->create(['google_id' => 'viewer-google-id', 'email' => self::EMAIL, ...$user]);
        GrowthViewer::factory()->create(['email' => self::EMAIL, 'google_id' => 'viewer-google-id', ...$grant]);
        app(SecondFactor::class)->enableTotp($person, app(SecondFactor::class)->newTotpSecret());

        return $person;
    }

    private function signedIn(User $person, array $session = []): void
    {
        $this->actingAs($person)->withSession([...$this->adminSession($person), ...$session]);
    }

    public function test_a_viewer_opens_the_growth_numbers_and_nothing_else(): void
    {
        $this->signedIn($this->viewer());

        $me = $this->getJson('/api/admin/me');
        $this->assertMatchesContract($me, 'admin.me', 200);
        $me->assertJsonPath('data.role', 'viewer');

        foreach (['/api/admin/growth', '/api/admin/growth/choirs', '/api/admin/reach', '/api/admin/funnel', '/api/admin/dropoffs', '/api/admin/sources'] as $open) {
            $this->getJson($open)->assertOk();
        }

        foreach (['/api/admin/overview', '/api/admin/choir', '/api/admin/choir/live', '/api/admin/revenue', '/api/admin/counts'] as $closed) {
            $this->getJson($closed)->assertForbidden()->assertJsonPath('code', 'not_admin');
        }
    }

    public function test_every_admin_route_not_on_the_list_refuses_a_viewer(): void
    {
        $this->signedIn($this->viewer());
        $checked = 0;
        $wrong = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            /** @var RoutingRoute $route */
            $middleware = $route->gatherMiddleware();

            if (! in_array('editor', $middleware, true) && ! in_array('admin', $middleware, true)) {
                continue;
            }

            $name = (string) $route->getName();
            // Each parameter a value its pattern takes, so the route itself is reached.
            $uri = '/'.ltrim((string) preg_replace_callback('/\{([^}?]+)\??\}/', static function (array $m) use ($route): string {
                $pattern = $route->wheres[$m[1]] ?? null;

                // whereIn's patterns are their words joined by "|": the first word is one.
                $words = $pattern !== null && preg_match('/^[a-z|_-]+$/', $pattern) === 1 ? explode('|', $pattern) : [];

                foreach (['1', '01234567-89ab-cdef-0123-456789abcdef', 'abcdefghijkl', 'ab', 'abcdefgh', 'abc123', ...$words] as $value) {
                    if ($pattern === null || preg_match('#^(?:'.$pattern.')$#', $value) === 1) {
                        return $value;
                    }
                }

                return 'x';
            }, $route->uri()), '/');

            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $checked++;
                $status = $this->json($method, $uri)->getStatusCode();

                if (GrowthViewers::mayOpen($name) ? $status === 403 : $status !== 403) {
                    $wrong[] = "{$method} {$uri} ({$name}): {$status}";
                }
            }
        }

        $this->assertGreaterThan(100, $checked, 'The admin routes were found.');
        $this->assertSame([], $wrong, 'A viewer is refused everywhere but the allow-list, and let in there.');
    }

    public function test_a_viewer_needs_all_three_checks_and_the_second_factor(): void
    {
        $person = $this->viewer();

        // A remember-me restore: no fresh Google sign-in this session.
        $this->signedIn($person, [AdminGate::SESSION_FRESH => null]);
        $this->getJson('/api/admin/growth')->assertForbidden();

        // Google confirmed another address this time.
        $this->flushSession();
        $this->signedIn($person, [AdminGate::SESSION_EMAIL => 'someone@example.com']);
        $this->getJson('/api/admin/growth')->assertForbidden();

        // The second factor not passed yet.
        $this->flushSession();
        $this->actingAs($person)->withSession($this->adminSession($person, false));
        $this->getJson('/api/admin/growth')->assertForbidden()->assertJsonPath('code', 'second_factor_required');

        // Taken away.
        GrowthViewer::query()->update(['revoked_at' => now()]);
        $this->flushSession();
        $this->signedIn($person);
        $this->getJson('/api/admin/growth')->assertForbidden();
        $this->getJson('/api/admin/me')->assertForbidden();
    }

    public function test_only_the_first_google_account_with_the_address_is_the_viewer(): void
    {
        $person = $this->viewer(grant: ['google_id' => null]);
        $this->signedIn($person);
        $this->getJson('/api/admin/growth')->assertForbidden();

        GrowthViewers::pin($person, 'Advisor@Example.com');
        $this->assertSame('viewer-google-id', GrowthViewer::query()->sole()->google_id);
        $this->getJson('/api/admin/growth')->assertOk();

        // Another Google account claiming the same address never takes it over.
        $other = User::factory()->create(['google_id' => 'another-google-id', 'email' => 'advisor.other@example.com']);
        GrowthViewers::pin($other, self::EMAIL);
        $this->assertSame('viewer-google-id', GrowthViewer::query()->sole()->google_id);
        $this->flushSession();
        $this->signedIn($other, [AdminGate::SESSION_EMAIL => self::EMAIL]);
        $this->getJson('/api/admin/growth')->assertForbidden();
    }

    public function test_a_suspended_viewer_sees_nothing(): void
    {
        $person = $this->viewer();
        $person->forceFill(['suspended_at' => now()])->save();
        $this->signedIn($person);
        $this->getJson('/api/admin/growth')->assertForbidden();
    }

    public function test_the_console_grants_with_one_invite_logs_it_and_takes_it_away(): void
    {
        Mail::fake();

        $this->artisan('app:viewer:grant', ['email' => 'not-an-address'])->assertFailed();
        $this->artisan('app:viewer:grant', ['email' => ' Advisor@Example.com ', '--note' => 'You also have view access to our search data.'])->assertSuccessful();

        $viewer = GrowthViewer::query()->sole();
        $this->assertSame([self::EMAIL, null, null], [$viewer->email, $viewer->google_id, $viewer->revoked_at]);
        Mail::assertQueued(GrowthViewerInvite::class, fn (GrowthViewerInvite $mail): bool => $mail->hasTo(self::EMAIL) && $mail->extra === 'You also have view access to our search data.');
        $this->assertSame(1, AuditLog::query()->where('action', 'admin.viewer.grant')->count());

        // Already a viewer: no second email.
        $this->artisan('app:viewer:grant', ['email' => self::EMAIL])->assertSuccessful();
        Mail::assertQueuedCount(1);

        $viewer->forceFill(['google_id' => 'viewer-google-id'])->save();
        $this->artisan('app:viewer:grant', ['email' => self::EMAIL, '--revoke' => true])->assertSuccessful();
        $this->assertNotNull($viewer->refresh()->revoked_at);
        $this->assertSame(1, AuditLog::query()->where('action', 'admin.viewer.revoke')->count());
        $this->assertNull(GrowthViewers::active(self::EMAIL));

        // Granted again: a fresh start, its Google account chosen again at the next sign-in.
        $this->artisan('app:viewer:grant', ['email' => self::EMAIL])->assertSuccessful();
        $this->assertSame([null, null], [$viewer->refresh()->revoked_at, $viewer->google_id]);
        Mail::assertQueuedCount(2);
    }

    public function test_the_invite_says_who_asked_without_a_name_what_they_see_and_how_to_sign_in_with_no_images(): void
    {
        $html = (new GrowthViewerInvite(self::EMAIL, 'A line *as written*.'))->to(self::EMAIL)->render();

        foreach (['Stackapella has given you', 'view-only', 'anonymous totals only', 'Sign in with Google', self::EMAIL, 'passkey', '/admin/sign-in', 'A line *as written*.'] as $words) {
            $this->assertStringContainsString(e($words), $html);
        }

        $this->assertStringNotContainsString('<img', $html);
        // Hillel's name is on the About page only (2026-11-27).
        $this->assertStringNotContainsString('Hillel', $html);
        $this->assertStringNotContainsString(e('A line *as written*.').'</strong>', $html);
        $this->assertSame('Stackapella invited you to see its growth numbers', (new GrowthViewerInvite(self::EMAIL))->envelope()->subject);
    }
}
