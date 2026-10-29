<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Actions\Trust\AddBlock;
use App\Enums\BlockType;
use App\Enums\SecurityEventType;
use App\Models\AuditLog;
use App\Models\Block;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Support\Flags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * The trust and safety screen's operations (contract/api.md).
 */
final class TrustAndSafetyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_flags_are_listed_with_who_changed_them(): void
    {
        $admin = $this->actingAsAdmin();
        app(Flags::class)->set('pause_signups', 'password', $admin, 'Bot sign-ups');

        $response = $this->getJson('/api/admin/flags');

        $this->assertMatchesContract($response, 'admin.flags.list', 200);
        $this->assertSame(array_keys((array) config('flags')), array_column($response->json('data'), 'name'));
        $response->assertJsonPath('data.1.value', 'password')
            ->assertJsonPath('data.1.reason', 'Bot sign-ups')
            ->assertJsonPath('data.1.changedBy.id', $admin->sqid())
            ->assertJsonPath('data.0.changedBy', null);
    }

    public function test_switching_a_flag_needs_sudo_and_a_reason_and_is_audited(): void
    {
        $admin = $this->actingAsAdmin();

        $response = $this->putJson('/api/admin/flags/shield_mode', ['value' => 'on', 'reason' => 'A wave of bot sign-ups']);

        $this->assertMatchesContract($response, 'admin.flags.update', 200);
        $response->assertJsonPath('data.value', 'on')->assertJsonPath('data.changedBy.name', $admin->name);
        $this->assertTrue(app(Flags::class)->on('shield_mode'));
        $this->assertSame(1, AuditLog::query()->where('action', 'flag.shield_mode')->count());

        $this->assertMatchesContract($this->putJson('/api/admin/flags/shield_mode', ['value' => 'maybe']), 'admin.flags.update', 422);
        $this->assertMatchesContract($this->putJson('/api/admin/flags/no_such_flag', ['value' => 'on', 'reason' => 'x']), 'admin.flags.update', 404);

        $this->travel(31)->minutes();
        $refused = $this->putJson('/api/admin/flags/shield_mode', ['value' => 'off', 'reason' => 'Over']);
        $this->assertMatchesContract($refused, 'admin.flags.update', 403);
        $refused->assertJsonPath('code', 'sudo_required');
        $this->assertTrue(app(Flags::class)->on('shield_mode'));
    }

    public function test_the_limits_are_listed_and_overridden_live(): void
    {
        $admin = $this->actingAsAdmin();

        $list = $this->getJson('/api/admin/limits');
        $this->assertMatchesContract($list, 'admin.limits.list', 200);
        $this->assertContains(['limiter' => 'contact', 'rule' => 'ip', 'max' => 5, 'per' => 'hour', 'by' => 'ip', 'default' => 5, 'override' => null, 'reason' => null, 'changedAt' => null, 'changedBy' => null], $list->json('data'));

        $response = $this->putJson('/api/admin/limits/contact/ip', ['max' => 2, 'reason' => 'Spam']);
        $this->assertMatchesContract($response, 'admin.limits.update', 200);
        $response->assertJsonPath('data.max', 2)->assertJsonPath('data.override', 2)->assertJsonPath('data.default', 5)
            ->assertJsonPath('data.reason', 'Spam')->assertJsonPath('data.changedBy.name', $admin->name);
        $this->assertMatchesContract($this->getJson('/api/admin/limits'), 'admin.limits.list', 200);

        $back = $this->putJson('/api/admin/limits/contact/ip', ['max' => null, 'reason' => 'Over']);
        $this->assertMatchesContract($back, 'admin.limits.update', 200);
        $back->assertJsonPath('data.max', 5)->assertJsonPath('data.override', null)->assertJsonPath('data.changedBy', null);

        $this->assertMatchesContract($this->putJson('/api/admin/limits/contact/ip', ['max' => 0, 'reason' => 'x']), 'admin.limits.update', 422);
        $this->assertMatchesContract($this->putJson('/api/admin/limits/contact/nope', ['max' => 3, 'reason' => 'x']), 'admin.limits.update', 404);
    }

    public function test_the_blocklist_is_listed_newest_first_a_page_at_a_time(): void
    {
        $this->actingAsAdmin();
        Block::factory()->count(52)->create(['created_by' => User::factory()]);
        $newest = Block::factory()->create(['type' => BlockType::IpRange, 'value' => '203.0.113.0/24']);

        $first = $this->getJson('/api/admin/blocklist');
        $this->assertMatchesContract($first, 'admin.blocklist.list', 200);
        $first->assertJsonCount(50, 'data')->assertJsonPath('data.0.id', $newest->sqid());
        $this->assertNotNull($first->json('meta.nextCursor'));

        $second = $this->getJson('/api/admin/blocklist?cursor='.$first->json('meta.nextCursor'));
        $this->assertMatchesContract($second, 'admin.blocklist.list', 200);
        $second->assertJsonCount(3, 'data')->assertJsonPath('meta.nextCursor', null);

        $ranges = $this->getJson('/api/admin/blocklist?type=ip_range');
        $ranges->assertJsonCount(1, 'data');
    }

    public function test_a_block_is_added_lower_cased_and_lifted_with_audit_entries(): void
    {
        $admin = $this->actingAsAdmin();

        $response = $this->postJson('/api/admin/blocklist', ['type' => 'email', 'value' => 'Bot@Spam-Farm.example', 'reason' => 'Sign-ups with someone else\'s address']);

        $this->assertMatchesContract($response, 'admin.blocklist.create', 201);
        $response->assertJsonPath('data.value', 'bot@spam-farm.example')->assertJsonPath('data.createdBy.id', $admin->sqid());
        $id = (string) $response->json('data.id');

        $this->assertMatchesContract($this->postJson('/api/admin/blocklist', ['type' => 'email', 'value' => 'bot@spam-farm.example', 'reason' => 'Again']), 'admin.blocklist.create', 422);

        $this->assertMatchesContract($this->deleteJson('/api/admin/blocklist/'.$id), 'admin.blocklist.delete', 204);
        $this->assertSame(0, Block::query()->count());
        $this->assertMatchesContract($this->deleteJson('/api/admin/blocklist/'.$id), 'admin.blocklist.delete', 404);
        $this->assertSame(['block.created', 'block.lifted'], AuditLog::query()->orderBy('id')->pluck('action')->all());
    }

    public function test_block_values_are_checked_for_their_type(): void
    {
        $this->actingAsAdmin();

        $bad = [
            ['email', 'not-an-email'],
            ['email_domain', 'no spaces.example'],
            ['ip', '999.1.1.1'],
            ['ip_range', '203.0.113.0'],
            ['ip_range', '10.0.0.0/4'],
            ['card_fingerprint', 'short'],
        ];

        foreach ($bad as [$type, $value]) {
            $response = $this->postJson('/api/admin/blocklist', ['type' => $type, 'value' => $value, 'reason' => 'x']);
            $this->assertMatchesContract($response, 'admin.blocklist.create', 422);
            $response->assertJsonPath('errors.value.0', __('validation.block_value.'.$type));
        }

        $this->postJson('/api/admin/blocklist', ['type' => 'ip_range', 'value' => '2001:db8::/32', 'reason' => 'x', 'expiresAt' => now()->addWeek()->toIso8601ZuluString()])->assertCreated();
        $this->assertMatchesContract($this->postJson('/api/admin/blocklist', ['type' => 'ip', 'value' => '203.0.113.5', 'reason' => 'x', 'expiresAt' => now()->subDay()->toIso8601ZuluString()]), 'admin.blocklist.create', 422);
    }

    public function test_security_events_are_listed_and_filtered(): void
    {
        $admin = $this->actingAsAdmin();
        SecurityEvent::factory()->count(5)->create(['user_id' => User::factory()]);
        SecurityEvent::factory()->create(['type' => SecurityEventType::AdminSignIn, 'user_id' => $admin->id, 'context' => ['method' => 'passkey']]);
        SecurityEvent::factory()->create(['ip' => null, 'context' => null]);

        $response = $this->getJson('/api/admin/security-events');
        $this->assertMatchesContract($response, 'admin.securityEvents.list', 200);
        $response->assertJsonCount(7, 'data')->assertJsonPath('data.0.ip', null)->assertJsonPath('data.0.context', null);
        $this->assertSame(12, strlen((string) $response->json('data.1.ipHash')));

        $mine = $this->getJson('/api/admin/security-events?user='.$admin->sqid());
        $this->assertMatchesContract($mine, 'admin.securityEvents.list', 200);
        $mine->assertJsonCount(1, 'data')->assertJsonPath('data.0.context.method', 'passkey');

        $this->getJson('/api/admin/security-events?type=admin_sign_in')->assertJsonCount(1, 'data');
    }

    public function test_the_audit_log_is_listed_and_filtered_by_prefix(): void
    {
        $admin = $this->actingAsAdmin();
        app(Flags::class)->set('read_only', 'on', $admin, 'An incident');
        AuditLog::record('admin.role.is_admin', $admin, null, before: ['is_admin' => false], after: ['is_admin' => true], reason: 'console');
        AuditLog::factory()->count(4)->create();

        $response = $this->getJson('/api/admin/audit');
        $this->assertMatchesContract($response, 'admin.audit.list', 200);
        $response->assertJsonCount(6, 'data');

        $roles = $this->getJson('/api/admin/audit?action=admin.role');
        $roles->assertJsonCount(1, 'data')->assertJsonPath('data.0.subject', ['type' => 'user', 'id' => $admin->sqid()])->assertJsonPath('data.0.actor', null);

        $this->getJson('/api/admin/audit?action=%25')->assertJsonCount(0, 'data');
    }

    public function test_values_are_stored_in_one_spelling_and_times_in_utc(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/admin/blocklist', ['type' => 'ip', 'value' => '2001:0DB8::0001', 'reason' => 'x'])->assertCreated()->assertJsonPath('data.value', '2001:db8::1');
        $this->postJson('/api/admin/blocklist', ['type' => 'ip_range', 'value' => '203.0.113.77/24', 'reason' => 'x'])->assertCreated()->assertJsonPath('data.value', '203.0.113.0/24');
        $this->assertMatchesContract($this->postJson('/api/admin/blocklist', ['type' => 'ip_range', 'value' => '203.0.113.5/24', 'reason' => 'again']), 'admin.blocklist.create', 422);

        $at = now()->addDay()->setTime(10, 0)->setTimezone('Europe/Kyiv');
        $response = $this->postJson('/api/admin/blocklist', ['type' => 'email', 'value' => 'x@spam.example', 'reason' => 'x', 'expiresAt' => $at->toIso8601String()]);
        $response->assertCreated()->assertJsonPath('data.expiresAt', $at->copy()->utc()->toIso8601ZuluString());

        $this->assertMatchesContract($this->postJson('/api/admin/blocklist', ['type' => 'email', 'value' => 'y@spam.example', 'reason' => 'x', 'expiresAt' => 'tomorrow']), 'admin.blocklist.create', 422);
    }

    public function test_adding_the_same_block_twice_at_once_is_a_validation_error(): void
    {
        $admin = User::factory()->admin()->create();
        $add = app(AddBlock::class);
        $add->handle(BlockType::Email, 'x@spam.example', 'x', null, $admin, null);

        $this->expectException(ValidationException::class);
        $add->handle(BlockType::Email, 'x@spam.example', 'x', null, $admin, null);
    }

    public function test_a_dotted_flag_name_is_not_a_flag(): void
    {
        $this->actingAsAdmin();

        $this->assertMatchesContract($this->putJson('/api/admin/flags/shield_mode.values', ['value' => 'on', 'reason' => 'x']), 'admin.flags.update', 404);
    }

    public function test_bad_filters_and_crafted_cursors_are_refused_not_500s(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/admin/blocklist?type=nope')->assertStatus(422)->assertJsonPath('code', 'validation_failed');
        $this->getJson('/api/admin/security-events?type=nope')->assertStatus(422);
        $this->getJson('/api/admin/audit?cursor='.base64_encode('{"_pointsToNextItems":true}'))->assertStatus(422)->assertJsonPath('errors.cursor.0', __('validation.cursor'));
        $this->getJson('/api/admin/blocklist?cursor=%%%')->assertStatus(422);
    }

    public function test_an_editor_cannot_reach_the_trust_and_safety_screen(): void
    {
        $this->actingAsEditor();

        foreach (['admin.flags.list' => '/api/admin/flags', 'admin.limits.list' => '/api/admin/limits', 'admin.blocklist.list' => '/api/admin/blocklist', 'admin.securityEvents.list' => '/api/admin/security-events', 'admin.audit.list' => '/api/admin/audit'] as $operation => $path) {
            $response = $this->getJson($path);
            $this->assertMatchesContract($response, $operation, 403);
            $response->assertJsonPath('code', 'not_admin');
        }

        $this->assertMatchesContract($this->putJson('/api/admin/flags/read_only', ['value' => 'on', 'reason' => 'x']), 'admin.flags.update', 403);
        $this->assertFalse(app(Flags::class)->on('read_only'));
    }

    public function test_the_admin_can_always_turn_read_only_off_again(): void
    {
        $admin = $this->actingAsAdmin();
        app(Flags::class)->set('read_only', 'on', $admin, 'An incident');

        $this->assertMatchesContract($this->putJson('/api/admin/flags/read_only', ['value' => 'off', 'reason' => 'Fixed']), 'admin.flags.update', 200);
    }
}
