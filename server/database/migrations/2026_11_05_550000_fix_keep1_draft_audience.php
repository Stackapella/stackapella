<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * keep1's draft gets its audience and guardrails (the coordinator, 2026-10-31): guests only, since
 * its card shows on days 7 to 13, when no device is "new" any more (that would enrol nobody) and the
 * first week is over; guardrails f_day14 and f_plus. Only while it's still a draft: a started keep1
 * keeps what it ran with. Never starts it.
 */
return new class extends Migration
{
    public function up(): void
    {
        $changed = DB::table('experiments')->where('key', 'keep1')->whereNull('starts_on')->update([
            'audience' => json_encode(['signedIn' => false]),
            'guardrails' => json_encode(['f_day14', 'f_plus']),
            'updated_at' => now(),
        ]);

        if ($changed > 0) {
            Cache::forget('experiments.all');
        }
    }

    public function down(): void
    {
        // Left in place: the draft's earlier audience enrolled nobody at its card.
    }
};
