<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Actions\Counts\RecordCounts;
use App\Actions\Templates\SyncOfficialTemplates;
use App\Support\Http\Hosts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The site's Share buttons (docs/retention-plan.md, "Sharing"; Hillel: "make sure people are sharing"): one secondary
 * beside the page's primary on a song's page and a part's, naming its surface and the page's own address, and its
 * share counted only for a real surface and method.
 */
final class ShareButtonsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_songs_page_and_a_parts_page_each_have_one_share_button_for_their_surface(): void
    {
        app(SyncOfficialTemplates::class)->handle();

        $song = (string) $this->get('/songs/amazing-grace')->assertOk()->getContent();
        $this->assertSame(1, substr_count($song, 'data-share '));
        $this->assertStringContainsString('data-surface="page-song"', $song);
        $this->assertStringContainsString('data-url="'.Hosts::publicUrl().'/songs/amazing-grace"', $song);
        $this->assertStringContainsString('/js/public/share.js', $song);

        $part = (string) $this->get('/songs/amazing-grace/alto')->assertOk()->getContent();
        $this->assertSame(1, substr_count($part, 'data-share '));
        $this->assertStringContainsString('data-surface="page-part"', $part);
        $this->assertStringContainsString('/songs/amazing-grace/alto"', $part);
        $this->assertStringContainsString('Learn the alto part of Amazing Grace, free', $part);

        // A collection's hero shares with its own short name; the carols with theirs.
        $hymns = (string) $this->get('/songs/hymns')->assertOk()->getContent();
        $this->assertStringContainsString('data-surface="page-collection"', $hymns);
        $this->assertStringContainsString('data-text="Learn Hymns, part by part, free"', $hymns);
        $carols = (string) $this->get('/songs/christmas-carols')->assertOk()->getContent();
        $this->assertStringContainsString('data-surface="page-carols"', $carols);
        $this->assertStringContainsString('Learn the Christmas carols, part by part, free', $carols);
    }

    public function test_the_buttons_shares_count_by_a_real_surface_and_method_and_their_tags_are_kept(): void
    {
        $this->postJson('/api/counts', ['events' => [['kind' => 'sh:page-part.sheet'], ['kind' => 'sh:page-song.copy'], ['kind' => 'sh:page-collection.copy'], ['kind' => 'sh:page-part.fax']]])->assertNoContent();

        $this->assertSame(['sh:page-collection.copy', 'sh:page-part.sheet', 'sh:page-song.copy'], DB::table('usage_counts')->orderBy('kind')->pluck('kind')->all());
        $this->assertSame('share-page-part', RecordCounts::source('share-page-part'));
        $this->assertSame('share-page-collection', RecordCounts::source('share-page-collection'));
    }
}
