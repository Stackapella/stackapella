<?php

declare(strict_types=1);

use App\Enums\OwnChoirStatus;
use App\Models\OwnChoirRequest;
use App\Support\OwnChoir\ChoirCounts;
use App\Support\Stats\ReportDay;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Admin > Growth's AI choirs (admin.growth.choirs) are counted from now as they happen; this fills their days before,
 * once, from the songs still on the server: songs made by plan, the part-minutes taken on, failures (ours, the take).
 * Only while none of those kinds has a count yet, so it never counts a song twice. A past song's free or comped is its
 * singer's plan now; a song deleted for good is missing. Friends' parts made (own_choir_friend_ready) were always counted.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('usage_counts')->whereIn('kind', ChoirCounts::GROWTH)->exists()) {
            return;
        }

        $day = static fn (Carbon $at): string => $at->copy()->setTimezone(ReportDay::zone())->toDateString();
        $counts = [];
        $add = static function (string $date, string $kind, int $n) use (&$counts): void {
            $counts[$date][$kind] = ($counts[$date][$kind] ?? 0) + $n;
        };

        OwnChoirRequest::query()->with('user')->whereNotNull('ready_at')->whereNull('parent_request_id')->where('remake', false)
            ->lazyById(200)->each(static fn (OwnChoirRequest $song) => $song->ready_at !== null ? $add($day($song->ready_at), 'choir_made_'.ChoirCounts::plan($song), 1) : null);

        OwnChoirRequest::query()->whereNotNull('first_claimed_at')
            ->lazyById(200)->each(static fn (OwnChoirRequest $song) => $song->first_claimed_at !== null ? $add($day($song->first_claimed_at), 'choir_part_seconds', (int) round($song->part_minutes * 60)) : null);

        OwnChoirRequest::query()->where('status', OwnChoirStatus::Failed->value)->whereIn('failure', array_keys(ChoirCounts::FAILED))
            ->lazyById(200)->each(static fn (OwnChoirRequest $song) => $song->updated_at !== null ? $add($day($song->updated_at), 'choir_failed_'.ChoirCounts::FAILED[(string) $song->failure], 1) : null);

        foreach ($counts as $date => $kinds) {
            foreach ($kinds as $kind => $n) {
                if ($n > 0) {
                    DB::statement('insert into usage_counts (day, kind, audience, country, count) values (?, ?, ?, ?, ?) on duplicate key update count = count + ?', [$date, $kind, 'account', '', $n, $n]);
                }
            }
        }
    }

    public function down(): void
    {
        // The counts stay: they are the days' numbers either way.
    }
};
