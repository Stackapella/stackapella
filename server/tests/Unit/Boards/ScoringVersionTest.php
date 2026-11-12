<?php

declare(strict_types=1);

namespace Tests\Unit\Boards;

use Tests\TestCase;

/**
 * The boards rank the scoring version the web app sends (web/js/scoring.js's SCORING_VERSION): a mismatch refused every
 * web score as scoring_outdated after the easier scoring went live (2026-11-12). They can't drift apart again.
 */
final class ScoringVersionTest extends TestCase
{
    public function test_the_server_ranks_the_scoring_version_the_app_sends(): void
    {
        preg_match('/export const SCORING_VERSION = (\\d+);/', (string) file_get_contents(base_path('../web/js/scoring.js')), $m);

        $this->assertSame((int) ($m[1] ?? 0), (int) config('boards.scoring_version'));
    }
}
