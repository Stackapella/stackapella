<?php

declare(strict_types=1);

namespace Tests;

use App\Models\User;
use App\Support\Admin\AdminGate;
use App\Support\Admin\SecondFactor;
use App\Support\Web\AppShell;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Contract\AssertsContract;
use Tests\Support\QueryGuard;

abstract class TestCase extends BaseTestCase
{
    use AssertsContract;

    protected function setUp(): void
    {
        parent::setUp();

        // Tests never reach the outside world. Fake what you need with Http::fake().
        Http::preventStrayRequests();

        Storage::fake('media', ['url' => 'https://stackapella.test/media']);
        Storage::fake('private', ['serve' => true]);
        Storage::fake('backup');

        Event::listen(QueryExecuted::class, static fn (QueryExecuted $query) => QueryGuard::query($query->sql));
    }

    /**
     * Every HTTP request a test makes is measured for N+1 queries on its own.
     *
     * @param  string  $method
     * @param  string  $uri
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $cookies
     * @param  array<string, mixed>  $files
     * @param  array<string, mixed>  $server
     * @param  string|null  $content
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null): TestResponse
    {
        // Each request starts without what the last one left in a scoped instance (App\Support\Flags).
        $this->app->forgetScopedInstances();
        QueryGuard::start();

        try {
            $response = parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
        } finally {
            $route = isset($response) ? $response->baseRequest?->route() : null;
            $found = QueryGuard::stop($route instanceof Route ? $route->getName() : null);
        }

        if ($found !== []) {
            $this->fail(QueryGuard::describe("{$method} {$uri}", $found));
        }

        return $response;
    }

    /**
     * Sign in as an admin who passes all three checks and the second factor.
     */
    protected function actingAsAdmin(?User $user = null, bool $secondFactorPassed = true): User
    {
        $user ??= User::factory()->admin()->create();
        app(SecondFactor::class)->enableTotp($user, app(SecondFactor::class)->newTotpSecret());

        $this->actingAs($user)->withSession($this->adminSession($user, $secondFactorPassed));

        return $user;
    }

    protected function actingAsEditor(bool $secondFactorPassed = true): User
    {
        return $this->actingAsAdmin(User::factory()->editor()->create(), $secondFactorPassed);
    }

    /**
     * The session of someone who signed in freshly with Google (and passed the second factor).
     *
     * @return array<string, mixed>
     */
    protected function adminSession(User $user, bool $secondFactorPassed = true): array
    {
        return array_filter([
            AdminGate::SESSION_FRESH => true,
            AdminGate::SESSION_EMAIL => (string) $user->email,
            SecondFactor::SESSION_PASSED_AT => $secondFactorPassed ? now()->getTimestamp() : null,
            SecondFactor::SESSION_USER => $secondFactorPassed ? $user->id : null,
            AdminGate::SESSION_UA => AdminGate::userAgentHash('Symfony'),
            AdminGate::SESSION_STARTED_AT => now()->getTimestamp(),
            AdminGate::SESSION_LAST_SEEN_AT => now()->getTimestamp(),
        ], static fn ($value): bool => $value !== null);
    }

    /**
     * The page is the app's shell, give or take its link preview (AppMeta, tested in
     * LinkPreviewsTest).
     */
    protected function assertShell(string|false $html, string $message = ''): void
    {
        $plain = static fn (string $page): string => (string) preg_replace(['#\s*<meta (?:property="og:|name="twitter:)[^>]*>#', '#<meta name="description"[^>]*>#'], ['', '<meta name="description">'], $page);
        $this->assertSame($plain(app(AppShell::class)->html()), $plain((string) $html), $message);
    }
}
