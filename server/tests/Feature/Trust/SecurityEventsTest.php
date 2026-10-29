<?php

declare(strict_types=1);

namespace Tests\Feature\Trust;

use App\Models\AuditLog;
use App\Models\SecurityEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

final class SecurityEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_raw_ips_are_gone_after_30_days_and_rows_after_a_year(): void
    {
        $recent = SecurityEvent::factory()->create(['created_at' => now()->subDays(29)]);
        $old = SecurityEvent::factory()->create(['created_at' => now()->subDays(31)]);
        $ancient = SecurityEvent::factory()->create(['created_at' => now()->subDays(366)]);

        $this->artisan('app:prune-security-events')->assertSuccessful();

        $this->assertNotNull($recent->refresh()->ip);
        $this->assertNull($old->refresh()->ip);
        $this->assertNotNull($old->ip_hash, 'The hash stays, so abuse can still be linked.');
        $this->assertModelMissing($ancient);
    }

    public function test_the_audit_log_is_append_only(): void
    {
        $log = AuditLog::factory()->create();

        try {
            $log->forceFill(['reason' => 'changed'])->save();
            $this->fail('An audit row was changed.');
        } catch (LogicException) {
            $this->assertSame('A test', $log->refresh()->reason);
        }

        $this->expectException(LogicException::class);
        $log->delete();
    }
}
