<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Pages\Landing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Landing::lineOf: any official song's opening line for a feature page's demo, as the hero's.
 */
final class LandingLineTest extends TestCase
{
    use RefreshDatabase;

    public function test_any_songs_line_comes_in_the_heros_shape_and_a_missing_one_is_null(): void
    {
        $this->assertNull(Landing::lineOf('no-such-song'));
        $this->assertSame(Landing::hero()['song'] ?? null, Landing::lineOf(Landing::HERO_SONG)['song'] ?? null);
    }
}
