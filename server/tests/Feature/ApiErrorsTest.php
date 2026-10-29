<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\Fixtures\PingController;
use Tests\Fixtures\ThrowingController;
use Tests\TestCase;

/**
 * The general rules' error shape (contract/api.md, "Errors").
 */
final class ApiErrorsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')->prefix('api')->group(function (): void {
            Route::post('/test/save', PingController::class);
            Route::get('/test/broken', ThrowingController::class);
        });
    }

    public function test_an_old_app_is_told_to_update(): void
    {
        config(['app.min_app_version' => '2026.10.20']);

        $this->getJson('/api/admin/me', ['X-App-Version' => '2026.10.1'])->assertStatus(426)->assertJsonPath('code', 'app_outdated');
        $this->getJson('/api/admin/me', ['X-App-Version' => '2026.10.26'])->assertStatus(401);
        $this->getJson('/api/admin/me')->assertStatus(401);
    }

    public function test_a_missing_csrf_token_is_csrf_mismatch(): void
    {
        // Laravel's tests skip CSRF unless asked; this turns the real check back on.
        $this->app->instance('env', 'local');

        $this->postJson('/api/test/save')->assertStatus(419)->assertJsonPath('code', 'csrf_mismatch');
    }

    public function test_a_server_error_never_shows_the_exception(): void
    {
        $this->withoutExceptionHandling([RuntimeException::class]);

        $response = $this->getJson('/api/test/broken');

        $response->assertStatus(500)->assertExactJson(['message' => 'Internal Server Error.', 'code' => 'server_error']);
        $this->assertStringNotContainsString('database password', (string) $response->getContent());
    }
}
