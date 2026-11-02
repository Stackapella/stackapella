<?php

declare(strict_types=1);

namespace Tests\Feature\Templates;

use App\Actions\Templates\SyncOfficialTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Each part's coarse shape on the catalogue, for Home's song covers: the same numbers as the app's
 * web/js/song-shape.js works out (frontend2's expected values), with no notes and no words.
 */
final class SongShapeTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_part_carries_its_phrases_place_and_lean(): void
    {
        app(SyncOfficialTemplates::class)->handle();

        $catalogue = $this->getJson('/api/templates');
        $this->assertMatchesContract($catalogue, 'templates.catalogue', 200);
        $songs = collect((array) $catalogue->json('data'))->keyBy('id');
        $parts = fn (string $song) => collect((array) $songs[$song]['parts'])->keyBy('key');

        $round = $parts('are-you-sleeping');
        // The round breathes after its held "John?" and "dong," (the singability pass, 2026-11-01).
        $this->assertSame([[0, 125, 0], [136, 170, 3], [182, 307, 0], [318, 352, -4]], array_slice($round['soprano']['shape'], 0, 4));
        $this->assertSame([91, 216, 0], $round['alto']['shape'][0]);
        $this->assertSame([182, 307, 0], $round['tenor']['shape'][0]);
        $this->assertSame([273, 398, 0], $round['bass']['shape'][0]);
        // Amazing Grace v4: a beat's breath before each new line (Zvi, a beta bass).
        $this->assertSame([[0, 229, -2], [250, 479, 2], [500, 729, 1], [750, 1000, -1]], $parts('amazing-grace')['soprano']['shape']);

        // Every part of every song has one, at most 24 phrases.
        foreach ($songs as $song) {
            foreach ((array) $song['parts'] as $part) {
                $this->assertLessThanOrEqual(24, count($part['shape']), $song['id']);
            }
        }
    }
}
