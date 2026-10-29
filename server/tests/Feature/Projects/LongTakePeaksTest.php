<?php

declare(strict_types=1);

namespace Tests\Feature\Projects;

use App\Models\Take;
use App\Support\Audio\Peaks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A take keeps at most 16384 peaks (contract/api.md, "Audio"), so a sync page of long takes fits
 * in memory.
 */
final class LongTakePeaksTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_long_take_gets_a_coarser_outline(): void
    {
        $this->assertSame(512, Peaks::framesForTake(48000 * 120));
        $this->assertSame(512, Peaks::framesForTake(512 * Peaks::MOST_FOR_A_TAKE));
        $this->assertSame(1024, Peaks::framesForTake(512 * Peaks::MOST_FOR_A_TAKE + 1));
        $this->assertSame(2048, Peaks::framesForTake(48000 * 600));
        $this->assertSame(8192, Peaks::framesForTake(192000 * 600));
        $this->assertSame([0.5, 0.9, 0.1], Peaks::thin([0.1, 0.5, 0.9, 0.2, 0.1], 2));
    }

    public function test_takes_stored_before_the_cap_are_thinned(): void
    {
        $frames = 48000 * 600;
        $long = Take::factory()->create(['frames' => $frames, 'frames_per_peak' => 512, 'peaks' => array_fill(0, (int) ceil($frames / 512), 0.25)]);
        $short = Take::factory()->create(['frames' => 48000, 'frames_per_peak' => 512, 'peaks' => array_fill(0, 94, 0.5)]);

        (require database_path('migrations/2026_11_04_100000_thin_long_take_peaks.php'))->up();

        $long->refresh();
        $this->assertSame(2048, $long->frames_per_peak);
        $this->assertCount((int) ceil($frames / 2048), (array) $long->peaks);
        $this->assertLessThanOrEqual(Peaks::MOST_FOR_A_TAKE, count((array) $long->peaks));
        $this->assertSame(512, $short->refresh()->frames_per_peak);
        $this->assertCount(94, (array) $short->peaks);
    }
}
