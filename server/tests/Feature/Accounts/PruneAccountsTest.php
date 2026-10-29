<?php

declare(strict_types=1);

namespace Tests\Feature\Accounts;

use App\Models\AppDevice;
use App\Models\DataExport;
use App\Models\EmailToken;
use App\Models\HandleHold;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class PruneAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_what_accounts_leave_behind_is_pruned_and_what_is_live_stays(): void
    {
        $user = User::factory()->create();
        $live = EmailToken::factory()->create(['user_id' => $user->id]);
        EmailToken::factory()->create(['user_id' => $user->id, 'used_at' => now()->subDays(2), 'created_at' => now()->subDays(2)]);
        EmailToken::factory()->create(['user_id' => $user->id, 'expires_at' => now()->subDays(2), 'created_at' => now()->subDays(3)]);

        $forever = HandleHold::factory()->create(['handle' => 'deleted_one']);
        HandleHold::factory()->create(['handle' => 'expired_one', 'held_until' => now()->subDay()]);

        $seen = UserDevice::factory()->create(['user_id' => $user->id]);
        UserDevice::factory()->create(['user_id' => $user->id, 'last_seen_at' => now()->subYears(2)]);

        $signedIn = AppDevice::factory()->create(['user_id' => $user->id, 'revoked_at' => now()->subDays(3)]);
        AppDevice::factory()->create(['user_id' => $user->id, 'revoked_at' => now()->subDays(31)]);
        AppDevice::factory()->create(['user_id' => $user->id, 'refresh_expires_at' => now()->subDays(31)]);

        Storage::disk('private')->put('exports/old.zip', 'zip');
        DataExport::factory()->create(['user_id' => $user->id, 'status' => 'ready', 'file_key' => 'exports/old.zip', 'ready_at' => now()->subDays(8), 'expires_at' => now()->subDay()]);
        $current = DataExport::factory()->create(['user_id' => $user->id, 'status' => 'ready', 'file_key' => 'exports/new.zip', 'ready_at' => now(), 'expires_at' => now()->addDays(7)]);

        $this->artisan('app:accounts:prune')->assertSuccessful();

        $this->assertSame([$live->id], EmailToken::query()->pluck('id')->all());
        $this->assertSame([$forever->id], HandleHold::query()->pluck('id')->all());
        $this->assertSame([$seen->id], UserDevice::query()->pluck('id')->all());
        $this->assertSame([$signedIn->id], AppDevice::query()->pluck('id')->all());
        $this->assertSame([$current->id], DataExport::query()->pluck('id')->all());
        Storage::disk('private')->assertMissing('exports/old.zip');
    }
}
