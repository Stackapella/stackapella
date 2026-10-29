<?php

declare(strict_types=1);

namespace Tests\Feature\Trust;

use App\Models\AuditLog;
use App\Models\FlagUser;
use App\Models\User;
use App\Support\Flags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use Tests\Fixtures\PingController;
use Tests\TestCase;

final class FlagsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')->prefix('api')->group(function (): void {
            Route::post('/test/upload', PingController::class)->middleware('paused:uploads');
            Route::post('/test/save', PingController::class);
            Route::get('/test/read', PingController::class);
        });
    }

    public function test_flags_start_off_and_change_with_an_audit_entry(): void
    {
        $flags = app(Flags::class);
        $this->assertSame('off', $flags->value('pause_signups'));
        $this->assertFalse($flags->on('read_only'));

        $flags->set('pause_signups', 'password', User::factory()->admin()->create(), 'Bot sign-ups');

        $this->assertSame('password', app(Flags::class)->value('pause_signups'));
        $log = AuditLog::query()->where('action', 'flag.pause_signups')->firstOrFail();
        $this->assertSame(['value' => 'off'], $log->before);
        $this->assertSame(['value' => 'password'], $log->after);
        $this->assertSame('Bot sign-ups', $log->reason);
    }

    public function test_a_value_outside_the_flags_values_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(Flags::class)->set('read_only', 'maybe', null, 'x');
    }

    public function test_a_pause_switch_stops_its_feature_with_423_paused(): void
    {
        $this->postJson('/api/test/upload')->assertOk();

        app(Flags::class)->set('pause_uploads', 'on', null, 'Storage attack');

        $this->postJson('/api/test/upload')->assertStatus(423)->assertExactJson(['message' => 'Locked.', 'code' => 'paused']);
        $this->postJson('/api/test/save')->assertOk();
    }

    public function test_read_only_stops_every_change_but_reading_and_the_admin(): void
    {
        app(Flags::class)->set('read_only', 'on', null, 'An incident');

        $this->postJson('/api/test/save')->assertStatus(423)->assertJsonPath('code', 'read_only');
        $this->getJson('/api/test/read')->assertOk();
    }

    public function test_a_dark_feature_can_be_on_for_one_person_first(): void
    {
        $tester = User::factory()->create();
        FlagUser::factory()->create(['flag' => 'gallery', 'user_id' => $tester->id]);

        $this->assertTrue(app(Flags::class)->onFor('gallery', $tester));
        $this->assertFalse(app(Flags::class)->onFor('gallery', User::factory()->create()));
        $this->assertFalse(app(Flags::class)->onFor('gallery', null));
    }

    public function test_the_console_command_lists_and_sets_flags(): void
    {
        $this->artisan('app:flag')->assertSuccessful();
        $this->artisan('app:flag read_only on')->assertFailed();
        $this->artisan('app:flag read_only on --reason=Incident')->assertSuccessful();

        $this->assertTrue(app(Flags::class)->on('read_only'));
    }

    public function test_a_json_response_helper_route_exists(): void
    {
        $this->assertInstanceOf(JsonResponse::class, (new PingController)());
    }
}
