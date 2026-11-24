<?php

declare(strict_types=1);

namespace App\Actions\SingingTest;

use App\Exceptions\Refusal;
use App\Models\AuditLog;
use App\Models\SingingTest;
use App\Models\User;

/**
 * Admin's look at singing tests held for the boards (admin.singingTests.*; the part boards' review, docs/singing-test-v1.md):
 * the held ones, cleared at once, or taken off the boards (they stay in the singer's history). Audited.
 */
final class SingingTestReview
{
    /**
     * @return list<array<string, mixed>>
     */
    public function flags(): array
    {
        return array_values(SingingTest::query()->with('user')->whereNotNull('flagged_at')->whereNull('reviewed_at')->whereNull('hidden_at')
            ->latest('flagged_at')->latest('id')->limit(100)->get()
            ->map(static fn (SingingTest $test): array => [
                'id' => $test->uuid,
                'person' => ['id' => $test->user->sqid(), 'name' => (string) $test->user->name],
                'testVersion' => $test->test_version,
                'total' => $test->total,
                'reasons' => array_values((array) $test->flag_reasons),
                'takenAt' => $test->taken_at->utc()->toIso8601ZuluString(),
                'holdEndsAt' => ($test->flagged_at ?? now())->copy()->addHours(SingingTest::HOLD_HOURS)->utc()->toIso8601ZuluString(),
            ])->all());
    }

    public function review(User $admin, string $id, ?string $ip): void
    {
        $test = $this->find($id);
        $test->forceFill(['reviewed_at' => now()])->save();
        AuditLog::record('singing_test.reviewed', $test->user, $admin, after: ['test' => $test->uuid], ip: $ip);
    }

    public function hide(User $admin, string $id, string $reason, ?string $ip): void
    {
        $test = $this->find($id);
        $test->forceFill(['hidden_at' => now()])->save();
        AuditLog::record('singing_test.hidden', $test->user, $admin, after: ['test' => $test->uuid], reason: $reason, ip: $ip);
    }

    private function find(string $id): SingingTest
    {
        return SingingTest::query()->with('user')->where('uuid', $id)->whereNull('hidden_at')->first() ?? throw new Refusal(404, 'not_found');
    }
}
