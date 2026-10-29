<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Actions\Templates\SyncOfficialTemplates;
use App\Http\Controllers\Public\DocumentPagesController;
use App\Models\Choir;
use App\Models\ChoirVoice;
use App\Models\CoachProfile;
use App\Models\Harmony;
use App\Models\Part;
use App\Models\Project;
use App\Models\Score;
use App\Models\Setting;
use App\Models\Take;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Support\Flags;
use App\Support\Sharing\HarmonyFiles;
use App\Support\Stats\Counts;
use App\Support\Stats\NetworkReadiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Counts from our own records, the network features panel, What's next's numbers, and the public
 * songs, guides and legal pages (contract/api.md, "Counts, the network panel and What's next (L)"
 * and "Songs, guides and legal pages (L)").
 */
final class CountsAndPagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The official songs, with Amazing Grace and Silent Night published.
     */
    private function publish(): void
    {
        $folder = storage_path('framework/testing/templates-'.uniqid());
        mkdir($folder, 0777, true);

        foreach (['amazing-grace', 'silent-night'] as $slug) {
            $data = (array) json_decode((string) file_get_contents(is_file(base_path('../web/templates/'.$slug.'.json')) ? base_path('../web/templates/'.$slug.'.json') : database_path('templates/'.$slug.'.json')), true);
            file_put_contents($folder.'/'.$slug.'.json', (string) json_encode([...$data, 'reviewed' => true]));
        }

        config(['templates.folders' => [$folder]]);
        app(SyncOfficialTemplates::class)->handle();
    }

    /**
     * A project from a song, with a ready take on each of its parts (sung by $by, the owner when null).
     */
    private function sang(User $owner, string $song, int $parts = 2, ?User $by = null): Project
    {
        $project = Project::factory()->create(['user_id' => $owner->id, 'template_slug' => $song, 'template_version' => 1]);

        for ($i = 0; $i < $parts; $i++) {
            $part = Part::factory()->create(['project_id' => $project->id, 'order' => $i, 'template_part_key' => 'p'.$i]);
            $take = Take::factory()->create(['part_id' => $part->id, 'recorded_by' => $by?->id]);
            $part->forceFill(['current_take_id' => $take->id])->save();
        }

        return $project;
    }

    public function test_a_finished_song_is_every_part_sung_by_its_owner(): void
    {
        $ana = User::factory()->create();
        $sam = User::factory()->create();
        $this->sang($ana, 'amazing-grace');
        $this->sang($ana, 'silent-night', 3);
        $this->sang($sam, 'amazing-grace');

        // Not finished: one part, a friend's take, a part with no take, or binned.
        $this->sang($sam, 'silent-night', 1);
        $this->sang($sam, 'happy-birthday', 2, $ana);
        $open = $this->sang($sam, 'michael-row');
        Part::factory()->create(['project_id' => $open->id, 'order' => 2, 'template_part_key' => 'p2']);
        $this->sang($sam, 'are-you-sleeping')->forceFill(['binned_at' => now()])->save();

        $this->assertSame(['finished' => 3, 'secondSongs' => 1], Counts::songs());
        // Ana sang Sam's Happy Birthday: both are active, and nobody else.
        User::factory()->create();
        $this->assertSame(2, Counts::activeSingers());

        $this->actingAsAdmin();
        $this->getJson('/api/admin/overview')->assertOk()
            ->assertJsonPath('data.harmonies.finished', 3)
            ->assertJsonPath('data.harmonies.secondSongs', 1)
            ->assertJsonPath('data.growth.activeSingers', 2)
            ->assertJsonPath('data.growth.upgrades', 0);
    }

    public function test_whats_next_shows_finishers_only_once_there_are_enough(): void
    {
        $this->publish();
        config(['templates.finishers_to_show' => 2, 'templates.also_learned_min' => 2]);
        [$ana, $sam, $kim] = User::factory()->count(3)->create()->all();
        $this->sang($ana, 'amazing-grace');
        $this->sang($ana, 'silent-night');
        $this->sang($sam, 'amazing-grace');
        $this->sang($sam, 'silent-night');
        $this->sang($kim, 'silent-night');

        $this->artisan('app:stats:nightly')->assertSuccessful();

        $night = $this->getJson('/api/templates/silent-night/finishers');
        $this->assertMatchesContract($night, 'templates.finishers', 200);
        $night->assertJsonPath('data.finishers', 3)->assertJsonPath('data.alsoLearned.0.id', 'amazing-grace');

        // A song too few of them share isn't named: the count shows, the list stays empty.
        config(['templates.also_learned_min' => 3]);
        $this->artisan('app:stats:nightly')->assertSuccessful();
        $this->getJson('/api/templates/silent-night/finishers')->assertJsonPath('data.finishers', 3)->assertJsonPath('data.alsoLearned', []);
        config(['templates.also_learned_min' => 2]);
        $this->artisan('app:stats:nightly')->assertSuccessful();

        config(['templates.finishers_to_show' => 20]);
        $this->getJson('/api/templates/silent-night/finishers')->assertJsonPath('data.finishers', null)->assertJsonPath('data.alsoLearned', []);
        $this->assertMatchesContract($this->getJson('/api/templates/nope/finishers'), 'templates.finishers', 404);
    }

    public function test_the_network_panel_counts_opt_ins_and_tells_the_digest_when_a_status_changes(): void
    {
        $owner = User::factory()->create(['adult_confirmed_at' => now()]);
        // Opted in, but never able to list: private, hidden, a minor's, a suspended owner's.
        Harmony::factory()->private()->create(['project_id' => null, 'user_id' => $owner->id, 'gallery_opt_in_at' => now()]);
        Harmony::factory()->create(['project_id' => null, 'user_id' => $owner->id, 'gallery_opt_in_at' => now(), 'hidden_at' => now()]);
        Harmony::factory()->create(['project_id' => null, 'gallery_opt_in_at' => now()]);
        Harmony::factory()->create(['project_id' => null, 'user_id' => User::factory()->suspended()->create(['adult_confirmed_at' => now()])->id, 'gallery_opt_in_at' => now()]);
        $harmony = Harmony::factory()->create(['project_id' => null, 'user_id' => $owner->id]);
        Storage::disk('private')->put((string) $harmony->audio_key, 'aac');
        Storage::disk('private')->put((string) $harmony->card_key, 'jpg');
        app(HarmonyFiles::class)->sync($harmony);
        $this->actingAs($owner);
        $updated = $this->patchJson('/api/harmonies/'.$harmony->public_id, ['inGallery' => true]);
        $this->assertMatchesContract($updated, 'harmonies.update', 200);
        $updated->assertJsonPath('data.inGallery', true);
        $this->assertNotNull($harmony->refresh()->gallery_opt_in_at);

        CoachProfile::factory()->count(2)->create(['status' => CoachProfile::APPROVED]);
        $network = app(NetworkReadiness::class);
        $this->assertSame(0, $network->recount());

        $this->actingAsAdmin();
        $shown = $this->getJson('/api/admin/network');
        $this->assertMatchesContract($shown, 'admin.network.show', 200);
        $shown->assertJsonPath('data.0.key', 'N1')->assertJsonPath('data.0.status', 'collecting')->assertJsonPath('data.0.numbers.0.value', 1);

        // Lower the gallery's lines: it's ready to open, and the digest hears of it once.
        $lowered = $this->putJson('/api/admin/network/N1', ['thresholds' => ['harmonies' => 1, 'singers' => 1]]);
        $this->assertMatchesContract($lowered, 'admin.network.update', 200);
        $lowered->assertJsonPath('data.0.status', 'ready_to_open');
        $this->assertMatchesContract($this->putJson('/api/admin/network/N1', ['thresholds' => ['coaches' => 1]]), 'admin.network.update', 422);
        $this->assertMatchesContract($this->putJson('/api/admin/network/N9', ['thresholds' => ['harmonies' => 1]]), 'admin.network.update', 404);

        $this->assertSame(1, $network->recount());
        $said = 'The network panel: The gallery is now ready to open (harmonies 1 of 1, singers 1 of 1).';
        $this->artisan('app:digest --print')->expectsOutputToContain($said)->assertSuccessful();
        $this->assertSame([$said], NetworkReadiness::changes());
        $this->artisan('app:digest')->assertSuccessful();
        $this->assertSame([], NetworkReadiness::changes());

        // Opened by its flag.
        app(Flags::class)->set('gallery', 'on', null, 'Opening the gallery');
        $this->getJson('/api/admin/network')->assertJsonPath('data.0.status', 'open');
        $this->assertEquals(['N1' => ['harmonies' => 1, 'singers' => 1]], Setting::query()->where('key', NetworkReadiness::THRESHOLDS)->sole()->value);
    }

    public function test_a_song_page_shows_its_parts_words_and_a_way_in(): void
    {
        $this->publish();

        $page = $this->get('/songs/silent-night')->assertOk()->assertViewIs('public.song');
        $song = $page->viewData('song');
        $this->assertSame('Silent Night', $song['title']);
        $this->assertSame(['key' => 'soprano', 'name' => 'Soprano', 'low' => 'C4', 'high' => 'F5'], $song['parts'][0]);
        $this->assertStringStartsWith('Silent night, Holy night', $song['words'][0]);
        $this->assertSame('/?song=silent-night&ref=song-page', $song['startUrl']);
        $this->assertSame('Learn the harmony to Silent Night, part by part', $page->viewData('meta')['title']);
        $this->assertSame('noindex', $page->headers->get('X-Robots-Tag'));
        $this->assertNull($page->headers->get('Set-Cookie'));

        $list = $this->get('/songs')->assertOk()->assertViewIs('public.songs');
        $this->assertSame(['amazing-grace', 'silent-night'], array_column($list->viewData('songs'), 'slug'));
        // Each card's Play: a free song's opening line; a Plus song's notes stay in the app.
        foreach ($list->viewData('songs') as $song) {
            $line = $list->viewData('lines')[$song['slug']];
            $song['plus'] ? $this->assertNull($line, $song['slug']) : $this->assertNotNull($line, $song['slug']);
        }

        $this->get('/songs/nope')->assertNotFound()->assertViewIs('public.page-gone')->assertViewHas('links', ['app' => '/songs']);
        // A singer's own template has no page.
        Template::factory()->create(['slug' => 'mine', 'owner_id' => User::factory()->create()->id]);
        $this->get('/songs/mine')->assertNotFound();
    }

    public function test_guides_and_legal_pages_come_from_their_text_as_blocks(): void
    {
        $this->publish();

        $guide = $this->get('/guides/how-to-sing-harmony')->assertOk()->assertViewIs('public.guide')->viewData('guide');
        $this->assertNotSame('', $guide['description']);
        $this->assertContains('list', array_column($guide['blocks'], 'type'));
        // Its song (Michael, Row) isn't published here, so the guide ends without one.
        $this->assertNull($guide['song']);
        foreach (['how-to-sing-harmony', 'find-your-part', 'practise-a-choir-part', 'a-round-for-beginners'] as $slug) {
            $this->get('/guides/'.$slug)->assertOk();
        }

        $legal = $this->get('/legal/security')->assertOk()->assertViewIs('public.legal')->viewData('document');
        $this->assertSame('security', $legal['page']);
        $this->assertFalse($legal['reviewed']);
        $this->get('/legal/copyright')->assertOk();
        // The beta agreement is the app's, not a public page.
        $this->get('/legal/beta-agreement')->assertNotFound();
        $this->get('/legal/nope')->assertNotFound()->assertViewIs('public.page-gone');
        $this->get('/guides/nope')->assertNotFound();

        // The beta agreement keeps its own fields.
        $this->actingAs(User::factory()->create())->getJson('/api/beta/agreement')->assertOk()->assertJsonMissingPath('data.song')->assertJsonMissingPath('data.description');
    }

    public function test_the_sitemap_lists_what_exists_and_robots_keeps_search_out_until_launch(): void
    {
        $this->publish();

        $map = $this->get('/sitemap.xml')->assertOk();
        $this->assertStringContainsString('<loc>https://stackapella.test/songs/silent-night</loc>', (string) $map->getContent());
        $this->assertStringContainsString('<loc>https://stackapella.test/guides/find-your-part</loc>', (string) $map->getContent());
        $this->assertStringContainsString('<loc>https://stackapella.test/legal/security</loc>', (string) $map->getContent());
        $this->assertStringContainsString('<loc>https://stackapella.test/pricing</loc>', (string) $map->getContent());
        $this->assertStringContainsString('<loc>https://stackapella.test/help/timing</loc>', (string) $map->getContent());
        $this->assertStringNotContainsString('/h/', (string) $map->getContent());

        $this->assertSame("User-agent: *\nDisallow: /\n", $this->get('/robots.txt')->assertOk()->getContent());

        config(['ops.indexing' => true]);
        $robots = (string) $this->get('/robots.txt')->getContent();
        $this->assertStringContainsString('Sitemap: https://stackapella.test/sitemap.xml', $robots);
        $this->assertStringContainsString('Disallow: /h/', $robots);
        $this->assertNull($this->get('/songs/silent-night')->headers->get('X-Robots-Tag'));

        // Search engines keep out of shared harmonies; the link-preview crawlers may read them, so a
        // share posted to Facebook, WhatsApp or X gets its card.
        $groups = array_values(array_filter(array_map('trim', explode("\n\n", $robots))));
        $everyone = (string) collect($groups)->first(fn (string $g): bool => str_starts_with($g, 'User-agent: *'));
        $previews = (string) collect($groups)->first(fn (string $g): bool => str_contains($g, 'User-agent: facebookexternalhit'));
        $this->assertStringContainsString('Disallow: /h/', $everyone);
        foreach (['Twitterbot', 'LinkedInBot', 'WhatsApp', 'Slackbot', 'Discordbot', 'redditbot'] as $bot) {
            $this->assertStringContainsString("User-agent: {$bot}\n", $previews);
        }
        $this->assertStringContainsString('Allow: /h/', $previews);
        $this->assertStringNotContainsString('Disallow: /h/', $previews);
        $this->assertStringContainsString('Disallow: /admin', $previews);
    }

    public function test_the_panel_counts_open_parts_and_the_fullest_choir(): void
    {
        app(Flags::class)->set('open_parts', 'on', null, 'Opening open parts');
        app(Flags::class)->set('virtual_choir', 'on', null, 'Opening the choirs');
        $owner = User::factory()->create();
        Harmony::factory()->create(['project_id' => null, 'user_id' => $owner->id, 'open_parts' => ['tenor']]);
        Harmony::factory()->create(['project_id' => null, 'user_id' => $owner->id, 'open_parts' => []]);
        $choir = Choir::factory()->create();
        ChoirVoice::factory()->count(2)->create(['choir_id' => $choir->id, 'part_key' => 'alto']);
        ChoirVoice::factory()->create(['choir_id' => $choir->id, 'part_key' => 'bass']);
        ChoirVoice::factory()->create(['choir_id' => $choir->id, 'part_key' => 'tenor', 'held_at' => now()]);
        ChoirVoice::factory()->create(['part_key' => 'alto']);
        Template::factory()->create(['owner_id' => $owner->id, 'visibility' => 'public']);

        $rows = collect(app(NetworkReadiness::class)->rows())->keyBy('key');
        $this->assertSame(1, collect($rows['N5']['numbers'])->firstWhere('key', 'openParts')['value']);
        $this->assertSame([3, 2], [collect($rows['N6']['numbers'])->firstWhere('key', 'voices')['value'], collect($rows['N6']['numbers'])->firstWhere('key', 'parts')['value']]);
        $this->assertSame(1, collect($rows['N3']['numbers'])->firstWhere('key', 'templates')['value']);
    }

    public function test_before_they_open_the_panel_counts_who_could_open_parts_and_join_a_choir(): void
    {
        $adult = User::factory()->create(['adult_confirmed_at' => now()]);
        $ours = TemplateVersion::factory()->create();
        $theirs = TemplateVersion::factory()->create(['template_id' => Template::factory()->create(['owner_id' => $adult->id])->id]);
        $hopeful = ['project_id' => null, 'user_id' => $adult->id, 'gallery_opt_in_at' => now(), 'take_ids' => ['a'], 'template_version_id' => $ours->id];
        Harmony::factory()->create($hopeful);
        // Not opted in, a private template, a single part, a minor's: none could open parts.
        Harmony::factory()->create([...$hopeful, 'gallery_opt_in_at' => null]);
        Harmony::factory()->create([...$hopeful, 'template_version_id' => $theirs->id]);
        Harmony::factory()->create([...$hopeful, 'take_ids' => null]);
        Harmony::factory()->create([...$hopeful, 'user_id' => User::factory()->create()->id]);

        $slug = $ours->template->slug;
        $score = ['take_uuid' => fake()->uuid(), 'template_slug' => $slug];
        foreach (['alto', 'alto', 'bass'] as $part) {
            Score::factory()->create([...$score, 'part_key' => $part, 'user_id' => User::factory()->create(['adult_confirmed_at' => now()])->id]);
        }
        // Too low, a minor, unverified, or a song that isn't ours: none counts.
        Score::factory()->create([...$score, 'user_id' => $adult->id, 'summary' => ['pitchPercent' => 59]]);
        Score::factory()->create($score);
        Score::factory()->create([...$score, 'user_id' => User::factory()->unverified()->create(['adult_confirmed_at' => now()])->id]);
        Score::factory()->create([...$score, 'template_slug' => $theirs->template->slug, 'user_id' => $adult->id]);

        $rows = collect(app(NetworkReadiness::class)->rows())->keyBy('key');
        $this->assertSame(1, collect($rows['N5']['numbers'])->firstWhere('key', 'openParts')['value']);
        $this->assertSame([3, 2], [collect($rows['N6']['numbers'])->firstWhere('key', 'voices')['value'], collect($rows['N6']['numbers'])->firstWhere('key', 'parts')['value']]);
    }

    public function test_no_public_legal_page_shows_a_blank_waiting_to_be_filled(): void
    {
        config(['site.legal_email' => 'law@example.com']);

        foreach (DocumentPagesController::LEGAL as $page) {
            $text = strip_tags((string) preg_replace('/<(script|style)\b.*?<\/\1>/s', '', (string) $this->get('/legal/'.$page)->assertOk()->getContent()));
            $this->assertDoesNotMatchRegularExpression('/\[[^\]]*\]/', $text, "/legal/{$page} shows a placeholder in brackets.");
            $this->assertStringNotContainsString('legal@stackapella.com', $text, "/legal/{$page} names the legal address the setting replaces.");
        }

        $this->assertStringContainsString('law@example.com', (string) $this->get('/legal/copyright')->getContent());
    }
}
