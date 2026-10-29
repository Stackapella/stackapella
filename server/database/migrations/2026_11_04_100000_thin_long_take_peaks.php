<?php

declare(strict_types=1);

use App\Support\Audio\Peaks;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // A take keeps at most Peaks::MOST_FOR_A_TAKE peaks (contract/api.md, "Audio"), so a sync page
    // of long takes fits in memory. Takes made before then are thinned the same way; a device that
    // already holds the finer outline keeps it (it's drawn by its own framesPerPeak).
    public function up(): void
    {
        DB::table('takes')->select(['id', 'frames', 'frames_per_peak'])
            ->whereNotNull('peaks')->whereRaw('JSON_LENGTH(peaks) > ?', [Peaks::MOST_FOR_A_TAKE])
            ->chunkById(50, function ($takes): void {
                foreach ($takes as $take) {
                    $per = Peaks::framesForTake((int) $take->frames);
                    $factor = intdiv($per, max(1, (int) $take->frames_per_peak));
                    $peaks = (array) json_decode((string) DB::table('takes')->where('id', $take->id)->value('peaks'), true);

                    DB::table('takes')->where('id', $take->id)->update([
                        'frames_per_peak' => $per,
                        'peaks' => json_encode(Peaks::thin(array_values($peaks), $factor)),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // The finer outlines aren't kept.
    }
};
