<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use Tests\TestCase;

/**
 * A public profile and Explore in the site's look, rendered from the gallery's view data: one h1,
 * each harmony's card linking to its page, the counts in words, the tabs marked.
 */
final class GalleryPagesTest extends TestCase
{
    /** @var array<string, mixed> */
    private const HARMONY = [
        'id' => 'h1', 'title' => 'Shenandoah at dusk', 'pageUrl' => 'https://stackapella.test/h/h1', 'audioUrl' => null, 'cardUrl' => 'https://media.test/cards/h1.png',
        'durationSeconds' => 90, 'song' => null, 'owner' => ['handle' => 'ana', 'name' => 'Ana', 'avatarUrl' => null],
        'singers' => [['name' => 'Ana', 'handle' => 'ana'], ['name' => 'Kim', 'handle' => null]], 'likes' => 2, 'comments' => 0, 'liked' => false,
        'commentsOff' => false, 'canComment' => false, 'featured' => true, 'openParts' => ['Tenor'], 'createdAt' => '2026-10-27T10:00:00Z',
    ];

    public function test_a_profile_shows_who_they_are_and_their_harmonies(): void
    {
        $html = view('public.profile', [
            'profile' => ['handle' => 'ana', 'name' => 'Ana <i>', 'avatarUrl' => null, 'bio' => 'Alto.', 'followers' => 1, 'following' => 3, 'harmonies' => 1, 'isMe' => false, 'isFollowing' => false, 'blocked' => false, 'canFollow' => false],
            'harmonies' => [self::HARMONY],
            'meta' => ['title' => 'Ana on Stackapella', 'description' => 'A harmony Ana sang.', 'url' => 'https://stackapella.test/@ana'],
        ])->render();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('Ana &lt;i&gt;', $html);
        $this->assertStringContainsString('href="https://stackapella.test/h/h1"', $html);
        $this->assertStringContainsString(e(trans_choice('pages.gallery_page.followers', 1, ['count' => 1])), $html);
        $this->assertStringContainsString(e(__('pages.gallery_page.open_part')), $html);
        $this->assertStringContainsString('width="1200" height="630"', $html);
    }

    public function test_explore_marks_its_tab(): void
    {
        $html = view('public.explore', [
            'tab' => 'featured',
            'harmonies' => [self::HARMONY],
            'meta' => ['title' => 'Explore harmonies on Stackapella', 'description' => 'Harmonies people sang.', 'url' => 'https://stackapella.test/explore'],
        ])->render();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertMatchesRegularExpression('#href="/explore\?tab=featured"\s+aria-current="page"#', $html);
        $this->assertStringContainsString(e(__('pages.gallery_page.featured')), $html);
    }
}
