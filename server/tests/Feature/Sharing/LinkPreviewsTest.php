<?php

declare(strict_types=1);

namespace Tests\Feature\Sharing;

use App\Actions\Templates\SyncOfficialTemplates;
use App\Jobs\MakeSongCard;
use App\Models\Group;
use App\Models\Project;
use App\Models\ProjectInvite;
use App\Models\Template;
use App\Models\User;
use App\Support\Sharing\SongCards;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * What an app link shows when it's pasted into a chat (AppMeta, SongCards): read by Slack,
 * WhatsApp, iMessage, X and Facebook with no cookie, no beta pass and no script. An invite names the
 * song and the part, never who sent it.
 */
final class LinkPreviewsTest extends TestCase
{
    use RefreshDatabase;

    private const SLACK = 'Slackbot-LinkExpanding 1.0 (+https://api.slack.com/robots)';

    /**
     * @return array{0: string, 1: Project}
     */
    private function invite(?string $song, ?string $part): array
    {
        $owner = User::factory()->create(['name' => 'Rivka Inviter']);
        $project = Project::factory()->for($owner)->create(['title' => 'Mum turns sixty', 'template_slug' => $song]);
        $token = Str::random(32);
        ProjectInvite::factory()->create(['project_id' => $project->id, 'token_hash' => hash('sha256', $token), 'part_key' => $part !== null ? 'alto' : null, 'part_name' => $part]);

        return [$token, $project];
    }

    private function og(string $html, string $property): ?string
    {
        return preg_match('#<meta (?:property|name)="'.preg_quote($property, '#').'" content="([^"]*)">#', $html, $m) === 1 ? html_entity_decode($m[1], ENT_QUOTES) : null;
    }

    public function test_an_invite_shows_the_song_and_part_and_never_who_sent_it(): void
    {
        Queue::fake();
        app(SyncOfficialTemplates::class)->handle();
        config(['app.coming_soon' => true]);
        [$token] = $this->invite('amazing-grace', 'Alto');

        // A chat app's fetcher: no cookie, no pass, no script.
        $html = (string) $this->withHeader('User-Agent', self::SLACK)->get('/join/'.$token)->assertOk()->getContent();
        $this->assertSame(__('site.meta.app.join.title_part', ['part' => 'Alto', 'song' => 'Amazing Grace']), $this->og($html, 'og:title'));
        $this->assertSame(__('site.meta.app.join.description'), $this->og($html, 'description'));
        $this->assertSame('summary_large_image', $this->og($html, 'twitter:card'));
        $this->assertSame('https://stackapella.test/join/'.$token, $this->og($html, 'og:url'));
        $this->assertSame(1, substr_count($html, '<meta name="description"'));
        $this->assertStringNotContainsString('Rivka', $html);
        $this->assertStringNotContainsString('Mum turns sixty', $html);

        // The song's card is made once, and the landing's stands in meanwhile.
        $this->assertSame(SongCards::fallback(), $this->og($html, 'og:image'));
        Queue::assertPushed(MakeSongCard::class, 1);
        $this->get('/join/'.$token);
        Queue::assertPushed(MakeSongCard::class, 1);
    }

    public function test_a_song_card_is_a_png_anyone_can_load_and_only_our_songs_and_public_ones_get_one(): void
    {
        Storage::fake('media');
        app(SyncOfficialTemplates::class)->handle();
        $song = Template::query()->official()->with('currentVersion')->where('slug', 'amazing-grace')->firstOrFail();
        $cards = app(SongCards::class);

        $cards->make($song, 'join');
        $url = $cards->url($song, 'join');
        $this->assertNotSame(SongCards::fallback(), $url);
        $key = Str::after($url, '/cards/');
        $png = (string) Storage::disk('media')->get('cards/'.$key);
        $this->assertSame("\x89PNG", substr($png, 0, 4));
        $size = getimagesizefromstring($png);
        $this->assertNotFalse($size);
        $this->assertSame([1200, 630, 'image/png'], [$size[0], $size[1], $size['mime']]);

        // A different wording is its own card; a private template's title never goes on one.
        $this->assertNotSame($url, (function () use ($cards, $song): string {
            $cards->make($song, 'listen');

            return $cards->url($song, 'listen');
        })());
        Queue::fake();
        $private = Template::factory()->create(['owner_id' => User::factory()->create()->id, 'visibility' => 'private']);
        $this->assertSame(SongCards::fallback(), $cards->url($private, 'join'));
        Queue::assertNothingPushed();
    }

    public function test_their_own_song_and_a_choir_say_what_the_link_is_for(): void
    {
        [$token] = $this->invite(null, 'Tenor');
        $html = (string) $this->get('/join/'.$token)->assertOk()->getContent();
        $this->assertSame(__('site.meta.app.default.title'), $this->og($html, 'og:title'));
        $this->assertSame(__('site.meta.app.join.description'), $this->og($html, 'og:description'));
        $this->assertStringNotContainsString('Mum turns sixty', $html);
        $this->assertStringNotContainsString('Rivka', $html);

        $choir = Str::random(32);
        Group::factory()->create(['name' => 'The Tuesday Singers', 'link_hash' => hash('sha256', $choir)]);
        $choirPage = (string) $this->get('/choir/'.$choir)->getContent();
        $this->assertSame(__('site.meta.app.choir.title'), $this->og($choirPage, 'og:title'));
        $this->assertStringNotContainsString('Tuesday', $choirPage);

        // Any other page, and a link that no longer opens anything, shows the app's own.
        $this->assertSame(__('site.meta.app.default.description'), $this->og((string) $this->get('/sing')->getContent(), 'og:description'));
        $this->assertSame(__('site.meta.app.default.description'), $this->og((string) $this->get('/join/'.Str::random(32))->getContent(), 'og:description'));
    }
}
