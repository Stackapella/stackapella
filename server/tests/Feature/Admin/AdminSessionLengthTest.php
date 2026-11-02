<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\Admin\AdminGate;
use App\Support\Ops\Readiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Tests\TestCase;

/**
 * Hillel: "can you make my session in the admin section last longer". A working day idle and a
 * week at most, in this browser only; sudo stays as short as it was. The app's Admin link shows
 * only for someone passing the whole gate.
 */
final class AdminSessionLengthTest extends TestCase
{
    use RefreshDatabase;

    private function adminStore(int $startedAgo, int $seenAgo): Store
    {
        $session = new Store('test', new ArraySessionHandler(60));
        $session->put(AdminGate::SESSION_STARTED_AT, now()->getTimestamp() - $startedAgo * 60);
        $session->put(AdminGate::SESSION_LAST_SEEN_AT, now()->getTimestamp() - $seenAgo * 60);
        $session->put(AdminGate::SESSION_UA, AdminGate::userAgentHash('UA'));

        return $session;
    }

    public function test_the_admin_session_lasts_a_working_day_idle_and_a_week_at_most_while_sudo_stays_short(): void
    {
        // The config's own defaults, whatever this machine's .env says.
        $defaults = $this->withoutEnv(['ADMIN_IDLE_TIMEOUT', 'ADMIN_MAX_LIFETIME', 'ADMIN_SUDO_WINDOW'], fn (): array => require config_path('admin.php'));
        $this->assertSame([480, 10080, 30], [$defaults['idle_timeout'], $defaults['max_lifetime'], $defaults['sudo_window']]);
        // The session itself never ends before the admin's idle limit, whatever SESSION_LIFETIME the host still has.
        $session = $this->withoutEnv(['ADMIN_IDLE_TIMEOUT', 'SESSION_LIFETIME'], fn (): array => (static function (): array {
            $_ENV['SESSION_LIFETIME'] = $_SERVER['SESSION_LIFETIME'] = '240';

            return require config_path('session.php');
        })());
        $this->assertSame(480, $session['lifetime']);
        config(['admin.idle_timeout' => 480, 'admin.max_lifetime' => 10080]);
        $gate = app(AdminGate::class);

        $this->assertTrue($gate->sessionIsFresh($this->adminStore(startedAgo: 6 * 24 * 60, seenAgo: 7 * 60 + 59), AdminGate::userAgentHash('UA')));
        $this->assertFalse($gate->sessionIsFresh($this->adminStore(startedAgo: 60, seenAgo: 8 * 60 + 1), AdminGate::userAgentHash('UA')));
        $this->assertFalse($gate->sessionIsFresh($this->adminStore(startedAgo: 7 * 24 * 60 + 1, seenAgo: 1), AdminGate::userAgentHash('UA')));
        // Still this browser only.
        $this->assertFalse($gate->sessionIsFresh($this->adminStore(startedAgo: 60, seenAgo: 1), AdminGate::userAgentHash('Another browser')));

        // The doctor says when the session itself would end sooner.
        $check = fn (): ?string => collect(app(Readiness::class)->run())->firstWhere('key', 'admin-session')?->status;
        config(['session.lifetime' => 480]);
        $this->assertSame('pass', $check());
        config(['session.lifetime' => 240]);
        $this->assertSame('fail', $check());
    }

    /**
     * @param  list<string>  $names
     * @param  callable(): array<string, mixed>  $read
     * @return array<string, mixed>
     */
    private function withoutEnv(array $names, callable $read): array
    {
        $saved = [];

        foreach ($names as $name) {
            $saved[$name] = [$_ENV[$name] ?? null, $_SERVER[$name] ?? null, getenv($name)];
            unset($_ENV[$name], $_SERVER[$name]);
            putenv($name);
        }

        try {
            return $read();
        } finally {
            foreach ($saved as $name => [$env, $server, $process]) {
                if ($env !== null) {
                    $_ENV[$name] = $env;
                }
                if ($server !== null) {
                    $_SERVER[$name] = $server;
                }
                if ($process !== false) {
                    putenv($name.'='.$process);
                }
            }
        }
    }

    public function test_the_admin_link_shows_only_for_someone_passing_the_whole_gate(): void
    {
        $this->actingAs(User::factory()->create()->fresh() ?? throw new \LogicException)->getJson('/api/account')->assertJsonPath('data.admin', false);
        // The database flag alone isn't enough.
        $this->actingAs(User::factory()->create(['is_admin' => true])->fresh() ?? throw new \LogicException)->getJson('/api/account')->assertJsonPath('data.admin', false);

        $this->actingAsAdmin(User::factory()->editor()->create()->fresh() ?? throw new \LogicException);
        $this->assertMatchesContract($this->getJson('/api/account'), 'account.show', 200);
        $this->getJson('/api/account')->assertJsonPath('data.admin', false);

        $this->actingAsAdmin(User::factory()->admin()->create()->fresh() ?? throw new \LogicException);
        $this->getJson('/api/account')->assertJsonPath('data.admin', true);
        $this->getJson('/api/auth/config')->assertJsonPath('data.sheetImport', false);
    }
}
