<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\Admin\SecondFactorReset;
use App\Support\Admin\SecondFactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class AdminCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_granting_the_admin_and_editor_roles_is_audited(): void
    {
        $user = User::factory()->create(['google_id' => 'admin-google-id']);

        $this->artisan('app:admin:grant admin-google-id')->assertSuccessful();
        $this->assertTrue($user->refresh()->is_admin);

        $this->artisan('app:admin:grant admin-google-id --editor')->assertSuccessful();
        $this->artisan('app:admin:grant admin-google-id --revoke')->assertSuccessful();
        $this->assertFalse($user->refresh()->is_admin);
        $this->assertTrue($user->is_editor);

        $this->assertSame(3, AuditLog::query()->where('subject_id', $user->id)->count());
        $this->artisan('app:admin:grant nobody')->assertFailed();
    }

    public function test_resetting_a_lost_second_factor_tells_every_admin(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        app(SecondFactor::class)->enableTotp($admin, app(SecondFactor::class)->newTotpSecret());

        $this->artisan('app:admin:reset-second-factor admin-google-id')->expectsConfirmation("Remove every passkey and the authenticator app for {$admin->name}?", 'yes')->assertSuccessful();

        $this->assertNull($admin->refresh()->secondFactor);
        Notification::assertSentTo($admin, SecondFactorReset::class);
    }
}
