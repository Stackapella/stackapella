<?php

declare(strict_types=1);

namespace Tests\Feature\Plus;

use App\Models\PushDevice;
use App\Models\User;
use App\Support\Reminders\PracticeReminders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Minishlink\WebPush\VAPID;
use Tests\Support\CloudSave;
use Tests\Support\Plus;
use Tests\TestCase;

final class ScoresAndRemindersTest extends TestCase
{
    use CloudSave;
    use Plus;
    use RefreshDatabase;

    public function test_everyones_scores_are_kept_and_the_history_is_plus(): void
    {
        $this->fakeStripe();
        $user = $this->member();
        $this->actingAs($user);
        $id = (string) Str::uuid();
        $score = ['id' => $id, 'lesson' => 'u4.l2.ex1', 'recordedAt' => now()->toIso8601String(), 'summary' => ['pitchPercent' => 81]];

        $made = $this->postJson('/api/scores', $score);
        $this->assertMatchesContract($made, 'scores.create', 201);
        $this->postJson('/api/scores', $score)->assertOk();
        $this->postJson('/api/scores', [...$score, 'summary' => ['pitchPercent' => 99]])->assertStatus(422)->assertJsonValidationErrors('id');
        $this->assertMatchesContract($this->postJson('/api/scores', [...$score, 'id' => (string) Str::uuid(), 'summary' => ['x' => str_repeat('a', 17 * 1024)]]), 'scores.create', 422);

        $this->assertMatchesContract($this->getJson('/api/scores'), 'scores.list', 403);

        $this->subscribe($user);
        $this->actingAs($this->fresh($user));
        $history = $this->getJson('/api/scores?lesson=u4.l2.ex1');
        $this->assertMatchesContract($history, 'scores.list', 200);
        $history->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id);
    }

    public function test_a_takes_report_joins_the_history_under_the_takes_id(): void
    {
        $this->owner();
        [, $part] = $this->projectWithPart();
        $take = $this->recordTake($part);

        $this->assertDatabaseHas('scores', ['uuid' => $take->uuid, 'take_uuid' => $take->uuid, 'part_key' => 'alto']);
    }

    public function test_the_reminder_settings_and_the_devices_by_their_token(): void
    {
        config(['services.vapid.public_key' => 'BPublicKey']);
        $user = $this->member();
        $this->actingAs($user);

        $saved = $this->putJson('/api/reminders', ['enabled' => true, 'days' => [5, 1, 3], 'time' => '18:30', 'timezone' => 'Europe/London']);
        $this->assertMatchesContract($saved, 'reminders.update', 200);
        $saved->assertJsonPath('data.days', [1, 3, 5])->assertJsonPath('data.vapidPublicKey', 'BPublicKey');
        $this->assertMatchesContract($this->putJson('/api/reminders', ['enabled' => true, 'days' => [1], 'time' => '18:20', 'timezone' => 'Europe/London']), 'reminders.update', 422);

        $endpoint = 'https://fcm.googleapis.com/fcm/send/abc';
        $device = $this->postJson('/api/reminders/devices', ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'BPk', 'auth' => 'au']]);
        $this->assertMatchesContract($device, 'reminders.devices.create', 201);
        $this->assertMatchesContract($this->postJson('/api/reminders/devices', ['endpoint' => 'http://not-https', 'keys' => []]), 'reminders.devices.create', 422);
        // Only a browser's push service: never somewhere else the server would post to.
        $this->postJson('/api/reminders/devices', ['endpoint' => 'https://169.254.169.254/latest', 'keys' => ['p256dh' => 'BPk', 'auth' => 'au']])->assertUnprocessable();
        $this->postJson('/api/reminders/devices', ['endpoint' => 'https://fcm.googleapis.com.evil.example/x', 'keys' => ['p256dh' => 'BPk', 'auth' => 'au']])->assertUnprocessable();
        $shown = $this->getJson('/api/reminders?endpoint='.urlencode($endpoint));
        $this->assertMatchesContract($shown, 'reminders.show', 200);
        $shown->assertJsonPath('data.thisDevice', true)->assertJsonPath('data.devices', 1);

        // The service worker: no session, only the token.
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $id = (string) $device->json('data.id');
        $token = (string) $device->json('data.token');

        $this->assertMatchesContract($this->putJson('/api/reminders/devices/'.$id, ['endpoint' => $endpoint.'2', 'keys' => ['p256dh' => 'BPk2', 'auth' => 'au2']], ['X-Device-Token' => 'wrong']), 'reminders.devices.update', 404);
        $this->assertMatchesContract($this->putJson('/api/reminders/devices/'.$id, ['endpoint' => $endpoint.'2', 'keys' => ['p256dh' => 'BPk2', 'auth' => 'au2']], ['X-Device-Token' => $token]), 'reminders.devices.update', 204);
        $this->assertSame($endpoint.'2', PushDevice::query()->value('endpoint'));

        $this->assertMatchesContract($this->deleteJson('/api/reminders/devices/'.$id, [], ['X-Device-Token' => $token]), 'reminders.devices.delete', 204);
        $this->assertSame(0, PushDevice::query()->count());

        // Safari's push service is one, and ten devices each at most: the oldest goes.
        $this->actingAs($user);
        PushDevice::factory()->count(10)->create(['user_id' => $user->id]);
        $this->postJson('/api/reminders/devices', ['endpoint' => 'https://web.push.apple.com/QAb', 'keys' => ['p256dh' => 'BPk', 'auth' => 'au']])->assertCreated();
        $this->assertSame(10, PushDevice::query()->where('user_id', $user->id)->count());
        $this->assertTrue(PushDevice::query()->where('endpoint', 'https://web.push.apple.com/QAb')->exists());
    }

    public function test_a_reminder_is_due_once_on_its_days_at_its_local_time(): void
    {
        $user = User::factory()->create(['reminder_enabled' => true, 'reminder_days' => [1], 'reminder_time' => '18:30', 'reminder_timezone' => 'America/New_York']);

        // Monday 18:30 in New York is 22:30 UTC (in October, EDT).
        $this->assertFalse(PracticeReminders::due($user, Carbon::parse('2026-10-26 22:15', 'UTC')));
        $this->assertTrue(PracticeReminders::due($user, Carbon::parse('2026-10-26 22:30', 'UTC')));
        $this->assertFalse(PracticeReminders::due($user, Carbon::parse('2026-10-27 22:30', 'UTC')), 'Tuesday is not a chosen day.');

        $user->forceFill(['reminder_sent_on' => '2026-10-26'])->save();
        $this->assertFalse(PracticeReminders::due($user, Carbon::parse('2026-10-26 22:45', 'UTC')), 'At most one a day.');

        // Without VAPID keys nothing is sent.
        $this->assertSame(0, app(PracticeReminders::class)->sendDue(now()));
    }

    public function test_a_device_off_the_push_services_list_is_dropped_never_sent_to(): void
    {
        $keys = VAPID::createVapidKeys();
        config(['services.vapid.public_key' => $keys['publicKey'], 'services.vapid.private_key' => $keys['privateKey'], 'services.vapid.subject' => 'mailto:hi@stackapella.test']);
        $user = User::factory()->create(['reminder_enabled' => true, 'reminder_days' => [1], 'reminder_time' => '18:30', 'reminder_timezone' => 'America/New_York']);
        $device = PushDevice::factory()->create(['user_id' => $user->id, 'endpoint' => 'https://internal.example/push/1']);

        $this->assertSame(1, app(PracticeReminders::class)->sendDue(Carbon::parse('2026-10-26 22:30', 'UTC')));
        $this->assertNull($device->fresh());
    }
}
