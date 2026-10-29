<?php

declare(strict_types=1);

namespace Tests\Feature\Gallery;

use App\Actions\Users\DeleteAccount;
use App\Models\BellItem;
use App\Models\Comment;
use App\Models\Follow;
use App\Models\Harmony;
use App\Models\HarmonyConsent;
use App\Models\Notice;
use App\Models\Report;
use App\Models\User;
use App\Support\Flags;
use App\Support\Sharing\HarmonyFiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The gallery (N1; contract/api.md, "The gallery (N1)"): profiles, harmonies listed, likes,
 * follows, blocks, comments, friends' consent, the bell and its review.
 */
final class GalleryTest extends TestCase
{
    use RefreshDatabase;

    private function open(): void
    {
        app(Flags::class)->set('gallery', 'on', null, 'Opening the gallery');
    }

    /**
     * Someone with a public profile at a trust level (2 by default), 18 or over.
     */
    private function singer(string $handle, int $level = 2, bool $public = true): User
    {
        return User::factory()->create([
            'handle' => $handle,
            'name' => ucfirst($handle),
            'adult_confirmed_at' => now(),
            'public_profile_at' => $public ? now() : null,
            'trust_level_override' => $level,
        ])->fresh() ?? throw new \LogicException;
    }

    /**
     * A live shared harmony of theirs, its files in place.
     *
     * @param  array<string, mixed>  $with
     */
    private function harmony(User $owner, array $with = []): Harmony
    {
        $harmony = Harmony::factory()->create(['project_id' => null, 'user_id' => $owner->id, ...$with]);
        Storage::disk('private')->put((string) $harmony->audio_key, 'aac');
        Storage::disk('private')->put((string) $harmony->card_key, 'jpg');
        app(HarmonyFiles::class)->sync($harmony);

        return $harmony->refresh();
    }

    /**
     * @param  array<string, mixed>  $with
     */
    private function listed(User $owner, array $with = []): Harmony
    {
        return $this->harmony($owner, ['gallery_opt_in_at' => now(), 'rights_confirmed_at' => now(), ...$with]);
    }

    public function test_nothing_shows_while_the_gallery_is_closed_but_the_bell_answers(): void
    {
        $ana = $this->singer('ana');
        $harmony = $this->listed($ana);

        $this->assertMatchesContract($this->getJson('/api/explore'), 'explore.list', 404);
        $this->assertMatchesContract($this->getJson('/api/profiles/ana'), 'profiles.show', 404);
        $this->get('/@ana')->assertNotFound();
        $this->get('/explore')->assertNotFound();

        $this->actingAs($this->singer('sam'));
        $this->assertMatchesContract($this->putJson('/api/harmonies/'.$harmony->public_id.'/like'), 'likes.create', 409);
        foreach ([
            ['comments.create', $this->postJson('/api/harmonies/'.$harmony->public_id.'/comments', ['body' => 'Lovely.'])],
            ['follows.create', $this->putJson('/api/profiles/ana/follow')],
        ] as [$operation, $refused]) {
            $this->assertMatchesContract($refused, $operation, 409);
            $refused->assertJsonPath('code', 'gallery_closed');
        }
        $bell = $this->getJson('/api/notifications');
        $this->assertMatchesContract($bell, 'notifications.list', 200);
        $bell->assertJsonPath('meta.unread', 0);
        $this->getJson('/api/account')->assertJsonPath('data.gallery', ['canPost' => false, 'canComment' => false, 'postsHeld' => false]);
    }

    public function test_a_public_profile_and_its_harmonies_in_explore_and_on_its_page(): void
    {
        $this->open();
        $ana = $this->singer('ana');
        $harmony = $this->listed($ana, ['title' => 'Shenandoah at dusk']);
        $this->listed($ana, ['gallery_held_at' => now()]);
        $this->listed($this->singer('kim', public: false));

        $explore = $this->getJson('/api/explore');
        $this->assertMatchesContract($explore, 'explore.list', 200);
        $explore->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $harmony->public_id)->assertJsonPath('data.0.owner.handle', 'ana')->assertJsonPath('data.0.canComment', false);
        $this->getJson('/api/explore?q=shenandoah')->assertJsonCount(1, 'data');
        $this->getJson('/api/explore?q=nothing-like-it')->assertJsonCount(0, 'data');
        $this->assertMatchesContract($this->getJson('/api/explore?tab=nope'), 'explore.list', 422);

        $profile = $this->getJson('/api/profiles/ana');
        $this->assertMatchesContract($profile, 'profiles.show', 200);
        $profile->assertJsonPath('data.harmonies', 1)->assertJsonPath('data.canFollow', false);
        $this->assertMatchesContract($this->getJson('/api/profiles/kim'), 'profiles.show', 404);
        $listed = $this->getJson('/api/profiles/ana/harmonies');
        $this->assertMatchesContract($listed, 'profiles.harmonies', 200);
        $this->assertMatchesContract($this->getJson('/api/profiles/nobody/harmonies'), 'profiles.harmonies', 404);

        $this->get('/@ana')->assertOk()->assertViewIs('public.profile')->assertViewHas('profile');
        $this->get('/explore')->assertOk()->assertViewIs('public.explore')->assertViewHas('tab', 'new');

        // Featured by Hillel, in sudo.
        $this->actingAsAdmin();
        $featured = $this->putJson('/api/admin/harmonies/'.$harmony->public_id.'/feature', ['featured' => true]);
        $this->assertMatchesContract($featured, 'admin.harmonies.feature', 200);
        $this->assertMatchesContract($this->putJson('/api/admin/harmonies/nope/feature', ['featured' => true]), 'admin.harmonies.feature', 404);
        $this->getJson('/api/explore?tab=featured')->assertJsonCount(1, 'data')->assertJsonPath('data.0.featured', true);
        $this->assertSame('featured', BellItem::query()->where('user_id', $ana->id)->value('kind'));
    }

    public function test_likes_and_follows_count_once_and_tell_the_bell(): void
    {
        $this->open();
        $ana = $this->singer('ana');
        $harmony = $this->listed($ana);
        $sam = $this->singer('sam');
        $this->actingAs($sam);

        $liked = $this->putJson('/api/harmonies/'.$harmony->public_id.'/like');
        $this->assertMatchesContract($liked, 'likes.create', 200);
        $this->putJson('/api/harmonies/'.$harmony->public_id.'/like')->assertJsonPath('data', ['liked' => true, 'likes' => 1]);
        $this->assertMatchesContract($this->putJson('/api/harmonies/nope/like'), 'likes.create', 404);
        $this->getJson('/api/explore')->assertJsonPath('data.0.liked', true);

        $followed = $this->putJson('/api/profiles/ana/follow');
        $this->assertMatchesContract($followed, 'follows.create', 200);
        $followed->assertJsonPath('data', ['following' => true, 'followers' => 1]);
        $this->getJson('/api/explore?tab=following')->assertJsonCount(1, 'data');

        // Another like the same day joins the same item in Ana's bell.
        $this->actingAs($this->singer('kim'));
        $this->putJson('/api/harmonies/'.$harmony->public_id.'/like')->assertOk();
        $this->actingAs($ana);
        $bell = $this->getJson('/api/notifications');
        $bell->assertJsonPath('meta.unread', 2)->assertJsonPath('data.1.kind', 'like')->assertJsonPath('data.1.others', 1);
        $read = $this->postJson('/api/notifications/read', ['all' => true]);
        $this->assertMatchesContract($read, 'notifications.read', 200);
        $read->assertJsonPath('data.unread', 0);
        $this->assertMatchesContract($this->postJson('/api/notifications/read', []), 'notifications.read', 422);

        $this->actingAs($sam);
        $this->assertMatchesContract($this->deleteJson('/api/harmonies/'.$harmony->public_id.'/like'), 'likes.delete', 200);
        $this->assertMatchesContract($this->deleteJson('/api/harmonies/nope/like'), 'likes.delete', 404);
        $this->assertMatchesContract($this->deleteJson('/api/profiles/ana/follow'), 'follows.delete', 200);
        $this->assertMatchesContract($this->deleteJson('/api/profiles/nobody/follow'), 'follows.delete', 404);
        $this->assertSame(1, $harmony->refresh()->likes_count);

        // A liker's account deleted: the count follows.
        app(DeleteAccount::class)->handle(User::query()->where('handle', 'kim')->firstOrFail(), '203.0.113.9');
        $this->assertSame(0, $harmony->refresh()->likes_count);
    }

    public function test_under_18s_and_new_accounts_are_kept_to_what_they_may_do(): void
    {
        $this->open();
        $ana = $this->singer('ana');
        $harmony = $this->listed($ana);
        $teen = User::factory()->create(['handle' => 'teen', 'adult_confirmed_at' => null, 'trust_level_override' => 2])->fresh() ?? throw new \LogicException;
        $this->actingAs($teen);

        $this->assertMatchesContract($this->putJson('/api/profiles/ana/follow'), 'follows.create', 403);
        $this->assertMatchesContract($this->postJson('/api/harmonies/'.$harmony->public_id.'/comments', ['body' => 'Hi']), 'comments.create', 403);
        $this->assertMatchesContract($this->patchJson('/api/account', ['publicProfile' => true]), 'account.update', 403);

        $this->actingAs($this->singer('newbie', 1));
        $refused = $this->postJson('/api/harmonies/'.$harmony->public_id.'/comments', ['body' => 'Hi']);
        $this->assertMatchesContract($refused, 'comments.create', 403);
        $refused->assertJsonPath('code', 'trust_required');
        $this->assertMatchesContract($this->getJson('/api/account'), 'account.show', 200);
    }

    public function test_comments_are_plain_held_by_the_word_lists_and_deleted_by_author_or_owner(): void
    {
        $this->open();
        $ana = $this->singer('ana');
        $harmony = $this->listed($ana);
        $sam = $this->singer('sam');
        $this->actingAs($sam);

        $made = $this->postJson('/api/harmonies/'.$harmony->public_id.'/comments', ['body' => 'Lovely blend on the last verse.']);
        $this->assertMatchesContract($made, 'comments.create', 201);
        $made->assertJsonPath('data.held', false);
        $link = $this->postJson('/api/harmonies/'.$harmony->public_id.'/comments', ['body' => 'Hear mine at www.example.com']);
        $this->assertMatchesContract($link, 'comments.create', 422);
        $link->assertJsonValidationErrors('body');
        $this->assertMatchesContract($this->postJson('/api/harmonies/nope/comments', ['body' => 'Hi']), 'comments.create', 404);

        $list = $this->getJson('/api/harmonies/'.$harmony->public_id.'/comments');
        $this->assertMatchesContract($list, 'comments.list', 200);
        $list->assertJsonCount(1, 'data')->assertJsonPath('data.0.mine', true);
        $this->assertMatchesContract($this->getJson('/api/harmonies/nope/comments'), 'comments.list', 404);
        $this->assertSame(1, $harmony->refresh()->comments_count);

        // Turned off by the owner.
        $this->actingAs($ana);
        $this->patchJson('/api/harmonies/'.$harmony->public_id, ['commentsOff' => true])->assertOk()->assertJsonPath('data.commentsOff', true);
        $this->actingAs($sam);
        $this->assertMatchesContract($this->postJson('/api/harmonies/'.$harmony->public_id.'/comments', ['body' => 'Again']), 'comments.create', 409);

        // The owner deletes it; someone else can't.
        $comment = Comment::query()->sole();
        $this->actingAs($this->singer('kim'));
        $this->assertMatchesContract($this->deleteJson('/api/comments/'.$comment->sqid()), 'comments.delete', 404);
        $this->actingAs($ana);
        $this->deleteJson('/api/comments/'.$comment->sqid())->assertNoContent();
        $this->assertSame(0, $harmony->refresh()->comments_count);
    }

    public function test_a_held_comment_and_a_new_singers_harmony_wait_for_review(): void
    {
        $this->open();
        $ana = $this->singer('ana');
        $harmony = $this->listed($ana);
        $sam = $this->singer('sam');
        $this->actingAs($sam);
        $this->postJson('/api/harmonies/'.$harmony->public_id.'/comments', ['body' => 'Call me on 555 0101 0101'])->assertCreated()->assertJsonPath('data.held', true);
        $this->actingAs($ana);
        $this->getJson('/api/harmonies/'.$harmony->public_id.'/comments')->assertJsonCount(0, 'data');

        // A level-2 singer's first harmony in the gallery waits.
        $mine = $this->harmony($ana);
        $this->patchJson('/api/harmonies/'.$mine->public_id, ['inGallery' => true, 'rightsConfirmed' => true])->assertOk()->assertJsonPath('data.galleryHeld', true);

        $this->actingAsAdmin();
        $held = $this->getJson('/api/admin/gallery/held');
        $this->assertMatchesContract($held, 'admin.gallery.held', 200);
        $held->assertJsonCount(1, 'data.harmonies')->assertJsonCount(1, 'data.comments');
        $comment = Comment::query()->sole();

        $hidden = $this->postJson('/api/admin/gallery/comment/'.$comment->sqid(), ['decision' => 'hide', 'note' => 'Shares a phone number.']);
        $this->assertMatchesContract($hidden, 'admin.gallery.decide', 200);
        $this->assertSame(Notice::COMMENT_HIDDEN, Notice::query()->where('user_id', $sam->id)->value('kind'));
        $this->assertMatchesContract($this->postJson('/api/admin/gallery/harmony/'.$mine->public_id, ['decision' => 'hide']), 'admin.gallery.decide', 422);
        $this->postJson('/api/admin/gallery/harmony/'.$mine->public_id, ['decision' => 'approve'])->assertOk()->assertJsonCount(0, 'data.harmonies');
        $this->assertMatchesContract($this->postJson('/api/admin/gallery/harmony/'.$mine->public_id, ['decision' => 'approve']), 'admin.gallery.decide', 409);
        $this->assertMatchesContract($this->postJson('/api/admin/gallery/comment/nope', ['decision' => 'approve']), 'admin.gallery.decide', 404);
        $this->getJson('/api/explore')->assertJsonCount(2, 'data');
    }

    public function test_the_gallery_needs_a_public_profile_the_rights_and_every_friends_yes(): void
    {
        $this->open();
        $ana = $this->singer('ana', 3, public: false);
        $sam = $this->singer('sam');
        $harmony = $this->harmony($ana, ['singer_ids' => [$sam->id]]);
        $this->actingAs($ana);

        $this->assertMatchesContract($this->patchJson('/api/harmonies/'.$harmony->public_id, ['inGallery' => true]), 'harmonies.update', 409);
        $asked = $this->postJson('/api/harmonies/'.$harmony->public_id.'/consent');
        $this->assertMatchesContract($asked, 'harmonies.consent.ask', 200);
        $asked->assertJsonPath('data.singers.0.consent', 'asked');
        $this->assertMatchesContract($this->postJson('/api/harmonies/nope/consent'), 'harmonies.consent.ask', 404);

        $this->actingAs($sam);
        $requests = $this->getJson('/api/consent-requests');
        $this->assertMatchesContract($requests, 'consentRequests.list', 200);
        $id = (string) $requests->json('data.0.id');
        $this->assertMatchesContract($this->postJson('/api/consent-requests/'.$id, []), 'consentRequests.decide', 422);
        $yes = $this->postJson('/api/consent-requests/'.$id, ['agree' => true]);
        $this->assertMatchesContract($yes, 'consentRequests.decide', 200);
        $this->actingAs($this->singer('kim'));
        $this->assertMatchesContract($this->postJson('/api/consent-requests/'.$id, ['agree' => true]), 'consentRequests.decide', 404);

        $this->actingAs($ana->fresh() ?? $ana);
        $this->assertMatchesContract($this->patchJson('/api/harmonies/'.$harmony->public_id, ['inGallery' => true, 'rightsConfirmed' => true]), 'harmonies.update', 409);
        $this->patchJson('/api/account', ['publicProfile' => true])->assertOk()->assertJsonPath('data.publicProfile', true);
        $this->patchJson('/api/harmonies/'.$harmony->public_id, ['inGallery' => true])->assertStatus(422)->assertJsonValidationErrors('rightsConfirmed');
        $this->patchJson('/api/harmonies/'.$harmony->public_id, ['inGallery' => true, 'rightsConfirmed' => true])->assertOk()->assertJsonPath('data.galleryHeld', false);
        $this->getJson('/api/explore')->assertJsonPath('data.0.singers.1.handle', 'sam');

        // Sam takes the yes back: out of the gallery.
        $this->actingAs($sam);
        $this->postJson('/api/consent-requests/'.$id, ['agree' => false])->assertOk();
        $this->assertNull($harmony->refresh()->gallery_opt_in_at);
        $this->assertSame(HarmonyConsent::DECLINED, HarmonyConsent::query()->value('status'));
        $this->actingAs($this->singer('newbie', 1));
        $this->patchJson('/api/account', ['publicProfile' => true])->assertOk();
        $newbies = $this->harmony(User::query()->where('handle', 'newbie')->firstOrFail());
        $this->assertMatchesContract($this->patchJson('/api/harmonies/'.$newbies->public_id, ['inGallery' => true, 'rightsConfirmed' => true]), 'harmonies.update', 403);
    }

    public function test_a_block_keeps_two_people_apart(): void
    {
        $this->open();
        $ana = $this->singer('ana');
        $harmony = $this->listed($ana);
        $sam = $this->singer('sam');
        $this->actingAs($sam);
        $this->putJson('/api/profiles/ana/follow')->assertOk();
        $this->postJson('/api/harmonies/'.$harmony->public_id.'/comments', ['body' => 'Nice one.'])->assertCreated();

        $this->actingAs($ana);
        $this->assertMatchesContract($this->putJson('/api/profiles/sam/block'), 'blocks.create', 204);
        $this->assertMatchesContract($this->putJson('/api/profiles/ana/block'), 'blocks.create', 422);
        $this->assertMatchesContract($this->putJson('/api/profiles/nobody/block'), 'blocks.create', 404);
        $blocks = $this->getJson('/api/account/blocks');
        $this->assertMatchesContract($blocks, 'blocks.list', 200);
        $blocks->assertJsonPath('data.0.handle', 'sam');
        $this->getJson('/api/harmonies/'.$harmony->public_id.'/comments')->assertJsonCount(0, 'data');

        $this->actingAs($sam);
        $this->getJson('/api/explore')->assertJsonCount(0, 'data');
        $this->getJson('/api/profiles/ana')->assertNotFound();
        $refused = $this->postJson('/api/harmonies/'.$harmony->public_id.'/comments', ['body' => 'Again.']);
        $this->assertMatchesContract($refused, 'comments.create', 403);
        $refused->assertJsonPath('code', 'blocked');
        // The follow between them ended with the block.
        $this->assertSame(0, Follow::query()->count());

        $this->actingAs($ana);
        $this->assertMatchesContract($this->deleteJson('/api/profiles/sam/block'), 'blocks.delete', 204);
        $this->assertMatchesContract($this->deleteJson('/api/profiles/nobody/block'), 'blocks.delete', 404);
    }

    public function test_reports_reach_comments_and_profiles(): void
    {
        $this->open();
        $ana = $this->singer('ana');
        $harmony = $this->listed($ana);
        $comment = Comment::factory()->create(['harmony_id' => $harmony->id, 'user_id' => $this->singer('sam')->id]);
        $harmony->forceFill(['comments_count' => 1])->save();

        foreach (['203.0.113.1', '203.0.113.2'] as $i => $ip) {
            $this->actingAs($this->singer('reporter'.$i));
            $this->withServerVariables(['REMOTE_ADDR' => $ip])->postJson('/api/reports', ['commentId' => $comment->sqid(), 'reason' => 'spam'])->assertNoContent();
        }

        $this->assertNotNull($comment->refresh()->held_at);
        $this->assertSame(0, $harmony->refresh()->comments_count);
        $this->postJson('/api/reports', ['handle' => 'ana', 'reason' => 'offensive'])->assertNoContent();
        $this->postJson('/api/reports', ['handle' => 'nobody', 'reason' => 'offensive'])->assertNotFound();
    }

    public function test_signed_out_reports_alone_never_hold_a_comment_and_an_answered_one_needs_new_people(): void
    {
        $this->open();
        $ana = $this->singer('ana');
        $harmony = $this->listed($ana);
        $comment = Comment::factory()->create(['harmony_id' => $harmony->id, 'user_id' => $this->singer('sam')->id]);
        $harmony->forceFill(['comments_count' => 1])->save();

        // Two networks signed out: recorded for Hillel, still shown.
        foreach (['203.0.113.1', '203.0.113.2'] as $ip) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip])->postJson('/api/reports', ['commentId' => $comment->sqid(), 'reason' => 'spam'])->assertNoContent();
        }
        $this->assertNull($comment->refresh()->held_at);

        // One signed in and verified makes it two people: held.
        $this->actingAs($this->singer('kim'));
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.3'])->postJson('/api/reports', ['commentId' => $comment->sqid(), 'reason' => 'spam'])->assertNoContent();
        $this->assertNotNull($comment->refresh()->held_at);

        // Hillel approves it: its reports are answered, so one more doesn't hold it again.
        $this->actingAsAdmin();
        $this->postJson('/api/admin/gallery/comment/'.$comment->sqid(), ['decision' => 'approve'])->assertOk();
        $this->assertSame(0, Report::query()->where('status', Report::OPEN)->count());
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->singer('lee'));
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.4'])->postJson('/api/reports', ['commentId' => $comment->sqid(), 'reason' => 'spam'])->assertNoContent();
        $this->assertNull($comment->refresh()->held_at);
    }

    public function test_a_report_sent_from_another_sites_page_is_answered_the_same_and_recorded_nowhere(): void
    {
        $this->open();
        $harmony = $this->listed($this->singer('ana'));
        $comment = Comment::factory()->create(['harmony_id' => $harmony->id, 'user_id' => $this->singer('sam')->id]);

        $this->withHeaders(['Origin' => 'https://elsewhere.example'])->postJson('/api/reports', ['commentId' => $comment->sqid(), 'reason' => 'spam'])->assertNoContent();
        $this->withHeaders(['Origin' => 'null'])->postJson('/api/reports', ['harmonyId' => $harmony->public_id, 'reason' => 'spam'])->assertNoContent();
        $this->withHeaders(['Sec-Fetch-Site' => 'cross-site'])->postJson('/api/reports', ['harmonyId' => $harmony->public_id, 'reason' => 'spam'])->assertNoContent();
        $this->assertSame(0, Report::query()->count());

        $this->withHeaders(['Origin' => rtrim((string) config('app.url'), '/'), 'Sec-Fetch-Site' => 'same-origin'])->postJson('/api/reports', ['harmonyId' => $harmony->public_id, 'reason' => 'spam'])->assertNoContent();
        $this->assertSame(1, Report::query()->count());
    }

    public function test_an_opt_in_from_before_the_gallery_opened_waits_for_the_rights_and_review(): void
    {
        $ana = $this->singer('ana');
        $harmony = $this->harmony($ana);
        $this->actingAs($ana);
        $this->patchJson('/api/harmonies/'.$harmony->public_id, ['inGallery' => true])->assertOk();

        $this->open();
        $this->getJson('/api/explore')->assertJsonCount(0, 'data');
        $this->patchJson('/api/harmonies/'.$harmony->public_id, ['rightsConfirmed' => true])->assertOk()->assertJsonPath('data.galleryHeld', true);
        $this->getJson('/api/explore')->assertJsonCount(0, 'data');

        // A trusted singer's isn't held, but still needs the rights.
        $kim = $this->singer('kim', 3);
        $hers = $this->harmony($kim);
        app(Flags::class)->set('gallery', 'off', null, 'Closing for a test');
        $this->actingAs($kim);
        $this->patchJson('/api/harmonies/'.$hers->public_id, ['inGallery' => true])->assertOk()->assertJsonPath('data.galleryHeld', false);
        $this->open();
        $this->getJson('/api/explore')->assertJsonCount(0, 'data');
        $this->patchJson('/api/harmonies/'.$hers->public_id, ['rightsConfirmed' => true])->assertOk();
        $this->getJson('/api/explore')->assertJsonCount(1, 'data');
    }

    public function test_under_18s_cant_say_yes_and_a_like_or_follow_rings_the_bell_once(): void
    {
        $this->open();
        $ana = $this->singer('ana', 3);
        $teen = User::factory()->create(['adult_confirmed_at' => null])->fresh() ?? throw new \LogicException;
        $harmony = $this->harmony($ana, ['singer_ids' => [$teen->id]]);
        $this->actingAs($ana);
        $this->postJson('/api/harmonies/'.$harmony->public_id.'/consent')->assertOk();

        $this->actingAs($teen);
        $id = (string) $this->getJson('/api/consent-requests')->json('data.0.id');
        $refused = $this->postJson('/api/consent-requests/'.$id, ['agree' => true]);
        $this->assertMatchesContract($refused, 'consentRequests.decide', 403);
        $refused->assertJsonPath('code', 'adult_required');
        $this->postJson('/api/consent-requests/'.$id, ['agree' => false])->assertOk();

        // Liking and following over and over: one bell item each.
        $listed = $this->listed($ana);
        $sam = $this->singer('sam');
        $this->actingAs($sam);
        foreach (range(1, 3) as $i) {
            $this->putJson('/api/harmonies/'.$listed->public_id.'/like')->assertOk();
            $this->deleteJson('/api/harmonies/'.$listed->public_id.'/like')->assertOk();
            $this->putJson('/api/profiles/ana/follow')->assertOk();
            $this->deleteJson('/api/profiles/ana/follow')->assertOk();
        }
        $this->assertSame(['follow', 'like'], BellItem::query()->where('user_id', $ana->id)->whereIn('kind', ['like', 'follow'])->orderBy('kind')->pluck('kind')->all());
        $this->assertSame(0, (int) BellItem::query()->where('user_id', $ana->id)->where('kind', 'like')->value('others'));

        // Following a profile that then goes private: unfollowing still works.
        $this->putJson('/api/profiles/ana/follow')->assertOk();
        $ana->forceFill(['public_profile_at' => null])->save();
        $this->deleteJson('/api/profiles/ana/follow')->assertOk()->assertJsonPath('data.following', false);
        $this->assertSame(0, Follow::query()->count());
    }
}
