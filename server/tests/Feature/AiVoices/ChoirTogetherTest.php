<?php

declare(strict_types=1);

namespace Tests\Feature\AiVoices;

use App\Actions\OwnChoir\ChoirTogether;
use App\Actions\Users\DeleteAccount;
use App\Enums\OwnChoirStatus;
use App\Models\AuditLog;
use App\Models\Harmony;
use App\Models\OwnChoirConsent;
use App\Models\OwnChoirInvite;
use App\Models\OwnChoirMember;
use App\Models\OwnChoirPart;
use App\Models\OwnChoirRequest;
use App\Models\OwnChoirVoice;
use App\Models\Template;
use App\Models\User;
use App\Models\UserLearning;
use App\Support\Errors\ScrubEvent;
use App\Support\Flags;
use App\Support\Ids;
use App\Support\Legal\LegalText;
use App\Support\OwnChoir\OwnChoir;
use App\Support\Sharing\HarmonyFacts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\BetaFixtures;
use Tests\Support\Sharing;
use Tests\Support\Wav;
use Tests\TestCase;

/**
 * A choir with friends (docs/choir-together-plan.md): invite, join, the friend's parts made in their voice and charged
 * to the owner, the owner's render kept and playing again, every way out clean, and nothing with the switch off.
 */
final class ChoirTogetherTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;
    use Sharing;

    private const TOKEN = 'test-worker-token-0123456789abcdef0123';

    private OwnChoirRequest $song;

    private User $ann;

    private User $ben;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        $this->catalogue();
        $flags = app(Flags::class);
        $flags->set('own_choir', 'on', null, 'test');
        $flags->set('own_choir_together', 'on', null, 'test');
        config(['services.ai_worker.choir_token' => self::TOKEN, 'services.turnstile.site_key' => '']);

        // Ann sang along to the bass and has Plus (paid, from a store), Ben's measured range is high.
        $this->ann = $this->singer('Ann Lee', ['store_plus_until' => now()->addDays(10)]);
        $this->ben = $this->singer('Ben Ode');
        UserLearning::factory()->create(['user_id' => $this->ben->id, 'progress' => ['range' => ['low' => 60, 'high' => 79], 'rangeSource' => 'measured']]);
        OwnChoirVoice::factory()->create(['user_id' => $this->ben->id]);
        $this->song = OwnChoirRequest::factory()->create(['user_id' => $this->ann->id, 'template_version_id' => (int) Template::query()->where('slug', 'amazing-grace')->value('current_version_id'), 'status' => OwnChoirStatus::Ready, 'tries' => 1, 'part_key' => 'bass', 'first_claimed_at' => now(), 'seen_at' => now()]);

        foreach (['soprano', 'alto', 'tenor', 'bass'] as $key) {
            OwnChoirPart::factory()->create(['own_choir_request_id' => $this->song->id, 'part_key' => $key, 'listen_key' => 'choir/parts/ann-'.$key.'-listen.m4a']);
        }
    }

    /**
     * @param  array<string, mixed>  $with
     */
    private function singer(string $name, array $with = []): User
    {
        $user = User::factory()->create(['name' => $name, 'adult_confirmed_at' => now(), ...$with])->fresh() ?? throw new \LogicException;
        OwnChoirConsent::factory()->create(['user_id' => $user->id, 'version' => OwnChoir::termsVersion()]);

        return $user;
    }

    private function as(User $user): void
    {
        $this->actingAs($user->fresh() ?? $user);
    }

    private function invite(): string
    {
        $this->as($this->ann);
        $made = $this->postJson("/api/own-choir/requests/{$this->song->sqid()}/invites")->assertCreated();

        return Str::afterLast((string) $made->json('data.url'), '/');
    }

    /** Join as the app does: read the exact parts this voice would sing, then say yes to them. */
    private function join(string $token, array $body): TestResponse
    {
        $query = isset($body['partKey']) ? '?partKey='.$body['partKey'].'&octave='.($body['octave'] ?? 0) : (($body['useSavedVoice'] ?? false) ? '?useSavedVoice=1' : '');
        $yours = $this->getJson("/api/own-choir/join/{$token}{$query}")->json('data.yourParts');

        return $this->postJson("/api/own-choir/join/{$token}", ['parts' => is_array($yours) ? $yours : [], ...$body]);
    }

    private function worker(string $method, string $uri, array $body = []): TestResponse
    {
        return $this->json($method, $uri, $body, ['Authorization' => 'Bearer '.self::TOKEN]);
    }

    /** The renderer claims Ben's job and sends his parts. */
    private function render(): OwnChoirRequest
    {
        $claim = $this->worker('POST', '/api/worker/choir/claim')->assertOk();
        $job = OwnChoirRequest::query()->findOrFail(Ids::decode((string) $claim->json('data.id')));
        $token = (string) $claim->json('data.claim');
        $wav = Wav::tone(1.0);

        foreach ($claim->json('data.parts') as $key) {
            $frames = intdiv(strlen($wav) - 44, 2);
            $this->worker('POST', "/api/worker/choir/jobs/{$job->sqid()}/parts", ['claim' => $token, 'part' => $key, 'sampleRate' => 48000, 'frames' => $frames, 'bytes' => strlen($wav), 'sha256' => hash('sha256', $wav),
                'checks' => ['pitchMaxCents' => 20.0, 'pitchMedianCents' => 8.0, 'notesMeasured' => 4, 'notesTotal' => 4, 'notesOver15' => 0, 'notesOver30' => 0, 'dropouts' => 0, 'heldSeconds' => 1.0, 'durationSeconds' => 1.0],
                'renderer' => ['model' => 'soulx-singer', 'codeRevision' => str_repeat('a', 40), 'weightsRevision' => str_repeat('b', 40), 'steps' => 32, 'control' => 'melody', 'pitchShift' => 0, 'reference' => 'own-clip']])->assertCreated();
            $part = OwnChoirPart::query()->where('own_choir_request_id', $job->id)->where('part_key', $key)->sole();
            Storage::disk('private')->put($part->upload_key, $wav);
            $this->worker('POST', "/api/worker/choir/parts/{$part->sqid()}/complete")->assertOk();
        }

        $this->worker('POST', "/api/worker/choir/jobs/{$job->sqid()}/finish", ['failed' => null, 'claim' => $token])->assertOk();

        return $job->refresh();
    }

    public function test_a_friend_joins_their_parts_are_made_in_their_voice_and_the_owners_play_again_when_they_leave(): void
    {
        $token = $this->invite();
        $this->assertMatchesContract($this->as($this->ann) ?? $this->postJson("/api/own-choir/requests/{$this->song->sqid()}/invites"), 'ownChoir.invites.create', 201);
        $state = $this->getJson('/api/own-choir')->assertOk();
        $state->assertJsonPath('data.requests.0.invite.canInvite', true)->assertJsonPath('data.requests.0.voices.0.fit', 'high');

        // Ben opens it, signed out, then joins with his saved voice.
        auth()->logout();
        $shown = $this->getJson("/api/own-choir/join/{$token}");
        $this->assertMatchesContract($shown, 'ownChoir.invites.show', 200);
        $shown->assertJsonPath('data.status', 'open')->assertJsonPath('data.owner', 'Ann')->assertJsonCount(4, 'data.listen');
        $this->as($this->ben);
        $joined = $this->join($token, ['showName' => false, 'useSavedVoice' => true]);
        $this->assertMatchesContract($joined, 'ownChoir.invites.accept', 201);
        $parts = (array) $joined->json('data.parts');
        $this->assertContains('soprano', $parts);
        $this->assertNotContains('bass', $parts);
        $this->getJson("/api/own-choir/join/{$token}")->assertJsonPath('data.status', 'used')->assertJsonCount(0, 'data.listen');

        // The renderer gets only Ben's parts, from Ben's voice, charged to Ann.
        $job = $this->render();
        $this->assertSame($parts, $job->only_parts);
        $this->assertSame($this->ben->id, $job->user_id);
        $this->assertSame($this->ann->id, $job->bill_to_user_id);
        $this->assertSame(OwnChoirStatus::Ready, $job->status);

        // Ann hears Ben's parts in place of hers, credited; her card shows again.
        $this->as($this->ann);
        $annSees = $this->getJson('/api/own-choir');
        $this->assertMatchesContract($annSees, 'ownChoir.show', 200);
        $annSees->assertJsonPath('data.requests.0.seen', false)->assertJsonPath('data.requests.0.members.0.name', 'Ben')->assertJsonPath('data.requests.0.members.0.status', 'ready');
        $soprano = collect($annSees->json('data.requests.0.voices'))->firstWhere('partKey', 'soprano');
        $this->assertSame(['friend', 'Ben', 'ready'], [$soprano['whose'], $soprano['name'], $soprano['status']]);
        $benPart = OwnChoirPart::query()->where('own_choir_request_id', $job->id)->where('part_key', 'soprano')->sole();
        $this->assertStringContainsString(basename((string) $benPart->listen_key), (string) collect($annSees->json('data.requests.0.parts'))->firstWhere('partKey', 'soprano')['url']);
        $this->assertLessThan(60.0, OwnChoir::quota($this->ann)['minutesLeft'], 'Ben\'s parts cost Ann\'s minutes.');
        $this->assertSame(1, OwnChoir::quota($this->ben)['freeLeft'], 'Ben keeps his own free song.');

        // Ben sees the choir he sings in, and marks his parts seen.
        $this->as($this->ben);
        $benSees = $this->getJson('/api/own-choir');
        $benSees->assertJsonPath('data.joined.0.member.seen', false)->assertJsonPath('data.joined.0.song.members', []);
        $this->assertSame('you', collect($benSees->json('data.joined.0.song.voices'))->firstWhere('partKey', 'soprano')['whose']);
        $this->postJson("/api/own-choir/requests/{$this->song->sqid()}/seen")->assertOk()->assertJsonPath('data.members', []);
        $this->getJson('/api/own-choir')->assertJsonPath('data.joined.0.member.seen', true);

        // He leaves: his renders go, Ann's play again.
        $member = OwnChoirMember::query()->sole();
        $files = OwnChoirPart::query()->where('own_choir_request_id', $job->id)->pluck('media_key')->all();
        $this->assertMatchesContract($this->deleteJson("/api/own-choir/members/{$member->sqid()}"), 'ownChoir.members.delete', 204);
        // The job's row stays, emptied, still counting toward Ann's minutes; Ben stays on record as having left.
        $this->assertSame(OwnChoirStatus::Deleted, $job->refresh()->status);
        $this->assertNotNull($member->refresh()->left_at);
        $this->assertTrue(AuditLog::query()->where('action', 'own_choir.member_left')->exists());
        foreach ($files as $file) {
            Storage::disk('private')->assertMissing((string) $file);
        }
        $this->as($this->ann);
        $after = $this->getJson('/api/own-choir');
        $this->assertStringContainsString('ann-soprano-listen', (string) collect($after->json('data.requests.0.parts'))->firstWhere('partKey', 'soprano')['url']);
        $after->assertJsonPath('data.requests.0.left.0.name', 'Ben')->assertJsonPath('data.requests.0.left.0.seen', false)->assertJsonPath('data.requests.0.members', []);
        $this->postJson("/api/own-choir/requests/{$this->song->sqid()}/seen")->assertOk();
        $this->getJson('/api/own-choir')->assertJsonPath('data.requests.0.left.0.seen', true);
        $this->assertMatchesContract($this->deleteJson("/api/own-choir/members/{$member->sqid()}"), 'ownChoir.members.delete', 404);
    }

    public function test_a_friends_new_clip_is_uploaded_checked_and_queued(): void
    {
        $token = $this->invite();
        $this->as($this->ben);
        $wav = Wav::tone(8.0);
        $joined = $this->join($token, ['showName' => true, 'partKey' => 'soprano', 'offsetFrames' => 24000, 'sampleRate' => 48000, 'frames' => intdiv(strlen($wav) - 44, 2), 'bytes' => strlen($wav), 'sha256' => hash('sha256', $wav)])->assertCreated();
        $joined->assertJsonPath('data.status', 'uploading');
        $member = OwnChoirMember::query()->sole();
        Storage::disk('private')->put((string) OwnChoirRequest::query()->where('own_choir_member_id', $member->id)->value('upload_key'), $wav);

        $done = $this->postJson("/api/own-choir/members/{$member->sqid()}/complete");
        $this->assertMatchesContract($done, 'ownChoir.members.complete', 200);
        $done->assertJsonPath('data.status', 'on_its_way');
        $this->assertMatchesContract($this->postJson('/api/own-choir/members/nope/complete'), 'ownChoir.members.complete', 404);
    }

    public function test_the_refusals(): void
    {
        $token = $this->invite();

        // Ann's own link, and a song not ready.
        $this->assertMatchesContract($this->join($token, ['showName' => false, 'useSavedVoice' => true]), 'ownChoir.invites.accept', 409);
        $queued = OwnChoirRequest::factory()->create(['user_id' => $this->ann->id, 'template_version_id' => $this->song->template_version_id, 'status' => OwnChoirStatus::Queued]);
        $this->assertMatchesContract($this->postJson("/api/own-choir/requests/{$queued->sqid()}/invites"), 'ownChoir.invites.create', 409);
        $this->assertMatchesContract($this->postJson('/api/own-choir/requests/nope/invites'), 'ownChoir.invites.create', 404);

        // No terms yet; no saved voice; a link that's gone.
        $cara = $this->singer('Cara');
        OwnChoirConsent::query()->where('user_id', $cara->id)->delete();
        $this->as($cara);
        $this->assertMatchesContract($this->join($token, ['showName' => false, 'useSavedVoice' => true]), 'ownChoir.invites.accept', 403);
        OwnChoirConsent::factory()->create(['user_id' => $cara->id, 'version' => OwnChoir::termsVersion()]);
        $this->join($token, ['showName' => false, 'useSavedVoice' => true])->assertStatus(409)->assertJsonPath('code', 'voice_required');
        $this->assertMatchesContract($this->join($token, []), 'ownChoir.invites.accept', 422);
        $this->assertMatchesContract($this->postJson('/api/own-choir/join/'.str_repeat('x', 24), ['parts' => [], 'showName' => false]), 'ownChoir.invites.accept', 404);
        $this->assertMatchesContract($this->getJson('/api/own-choir/join/'.str_repeat('x', 24)), 'ownChoir.invites.show', 404);

        // Ben joins; again is already_member; the used link is closed for anyone else.
        $this->as($this->ben);
        $this->join($token, ['showName' => false, 'useSavedVoice' => true])->assertCreated();
        $this->join($token, ['showName' => false, 'useSavedVoice' => true])->assertStatus(409);
        OwnChoirVoice::factory()->create(['user_id' => $cara->id]);
        $this->as($cara);
        $this->join($token, ['showName' => false, 'useSavedVoice' => true])->assertStatus(409)->assertJsonPath('code', 'invite_closed');

        // Revoked, by its owner only.
        $this->as($this->ann);
        $another = $this->postJson("/api/own-choir/requests/{$this->song->sqid()}/invites")->assertCreated();
        $invite = OwnChoirInvite::query()->whereNull('used_at')->latest('id')->firstOrFail();
        $this->as($this->ben);
        $this->assertMatchesContract($this->deleteJson("/api/own-choir/invites/{$invite->sqid()}"), 'ownChoir.invites.delete', 404);
        $this->as($this->ann);
        $this->assertMatchesContract($this->deleteJson("/api/own-choir/invites/{$invite->sqid()}"), 'ownChoir.invites.delete', 204);
        $this->getJson('/api/own-choir/join/'.Str::afterLast((string) $another->json('data.url'), '/'))->assertJsonPath('data.status', 'revoked');

        // Too many invites a day.
        config(['own_choir.together.invites_a_day' => 2]);
        $this->assertMatchesContract($this->postJson("/api/own-choir/requests/{$this->song->sqid()}/invites"), 'ownChoir.invites.create', 429);
    }

    public function test_the_free_song_has_one_friend_and_plus_more_never_every_part(): void
    {
        $this->ann->forceFill(['store_plus_until' => null])->save();
        $token = $this->invite();
        $this->as($this->ben);
        $this->join($token, ['showName' => false, 'useSavedVoice' => true])->assertCreated();

        $this->as($this->ann);
        $this->getJson('/api/own-choir')->assertJsonPath('data.requests.0.invite.reason', 'plus_required');
        $this->assertMatchesContract($this->postJson("/api/own-choir/requests/{$this->song->sqid()}/invites"), 'ownChoir.invites.create', 403);

        // With Plus, up to the parts minus one.
        $this->ann->forceFill(['store_plus_until' => now()->addDays(10)])->save();
        foreach (['Cara', 'Dan'] as $name) {
            OwnChoirMember::factory()->create(['own_choir_request_id' => $this->song->id, 'user_id' => $this->singer($name)->id, 'parts' => []]);
        }
        $this->as($this->ann);
        $this->assertMatchesContract($this->postJson("/api/own-choir/requests/{$this->song->sqid()}/invites"), 'ownChoir.invites.create', 409);
    }

    public function test_a_friends_parts_wait_for_the_owners_minutes(): void
    {
        config(['own_choir.plus_minutes' => 0.5]);
        $token = $this->invite();
        $this->as($this->ben);
        $this->join($token, ['showName' => false, 'useSavedVoice' => true])->assertCreated()->assertJsonPath('data.status', 'waiting_minutes');
        $this->assertGreaterThan(0, OwnChoirMember::query()->with(['job', 'song.user', 'user'])->sole()->job?->part_minutes);

        // Not claimed while waiting; on its way once Ann has the minutes.
        $this->worker('POST', '/api/worker/choir/claim')->assertNoContent();
        $this->as($this->ann);
        $waiting = $this->getJson('/api/own-choir')->assertJsonPath('data.requests.0.members.0.status', 'waiting_minutes');
        $this->assertGreaterThan(0, $waiting->json('data.requests.0.members.0.minutesShort'));
        config(['own_choir.plus_minutes' => 60]);
        $this->artisan('app:own-choir:prune')->assertSuccessful();
        $this->worker('POST', '/api/worker/choir/claim')->assertOk();
    }

    public function test_everything_ends_cleanly_when_a_friend_deletes_their_voice_or_account_or_the_owner_the_song(): void
    {
        $join = function (User $who): OwnChoirMember {
            $token = $this->invite();
            $this->as($who);
            $this->join($token, ['showName' => false, 'useSavedVoice' => true])->assertCreated();

            return OwnChoirMember::query()->where('user_id', $who->id)->sole();
        };

        // Ben deletes his voice: his part in Ann's song goes.
        $join($this->ben);
        $this->deleteJson('/api/own-choir/voice')->assertNoContent();
        $this->assertSame(0, OwnChoirMember::query()->whereNull('left_at')->count());

        // Ben's account goes: the same; Ann's song stays.
        OwnChoirVoice::factory()->create(['user_id' => $this->ben->id]);
        $join($this->ben);
        app(DeleteAccount::class)->handle($this->ben->fresh() ?? $this->ben, '203.0.113.7');
        $this->assertSame(0, OwnChoirMember::query()->whereNull('left_at')->count());
        $this->assertSame(0, OwnChoirMember::query()->whereNotNull('user_id')->count(), 'Nothing ties a row to his account now; Ann\'s notice keeps his first name.');
        $this->assertSame(OwnChoirStatus::Ready, $this->song->refresh()->status);

        // Ann deletes her song: every friend's part goes with it.
        $cara = $this->singer('Cara');
        OwnChoirVoice::factory()->create(['user_id' => $cara->id]);
        UserLearning::factory()->create(['user_id' => $cara->id, 'progress' => ['range' => ['low' => 60, 'high' => 79], 'rangeSource' => 'measured']]);
        $join($cara);
        $this->as($this->ann);
        $this->deleteJson("/api/own-choir/requests/{$this->song->sqid()}")->assertNoContent();
        $this->assertSame(0, OwnChoirMember::query()->whereNull('left_at')->count());
        $this->assertSame(0, OwnChoirInvite::query()->count());
        $this->assertSame(0, OwnChoirRequest::query()->whereNotNull('parent_request_id')->where('status', '!=', OwnChoirStatus::Deleted->value)->count());
    }

    public function test_with_the_switch_off_nothing_changes(): void
    {
        $token = $this->invite();
        app(Flags::class)->set('own_choir_together', 'off', null, 'test');

        $this->as($this->ann);
        $state = $this->getJson('/api/own-choir')->assertOk();
        $state->assertJsonPath('data.requests.0.voices', [])->assertJsonPath('data.requests.0.members', [])->assertJsonPath('data.requests.0.invite', null)->assertJsonPath('data.joined', []);
        $this->getJson('/api/auth/config')->assertJsonPath('data.ownChoirTogether', false);
        $this->postJson("/api/own-choir/requests/{$this->song->sqid()}/invites")->assertNotFound();
        $this->getJson("/api/own-choir/join/{$token}")->assertNotFound();
        $this->as($this->ben);
        $this->join($token, ['showName' => false, 'useSavedVoice' => true])->assertNotFound();
    }

    public function test_the_join_page_never_leaks_its_token(): void
    {
        $page = $this->get('/choir/join/'.str_repeat('a', 24))->assertOk();

        $page->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertStringContainsString('no-store', (string) $page->headers->get('Cache-Control'));
    }

    public function test_a_share_credits_a_friends_parts_as_a_friend_unless_they_show_their_name(): void
    {
        $token = $this->invite();
        $this->as($this->ben);
        $this->join($token, ['showName' => false, 'useSavedVoice' => true])->assertCreated();
        $this->render();

        $this->as($this->ann);
        $project = (string) Str::uuid();
        $this->saveProject($project, 0, ['settings' => ['choir' => ['requestId' => $this->song->sqid()]]])->assertOk();
        $shared = $this->startHarmony($project, Wav::stereo(2.0))->assertCreated();
        $harmony = Harmony::query()->where('public_id', (string) $shared->json('data.id'))->firstOrFail();

        $this->assertNotEmpty($harmony->own_voice_friends);
        $this->assertNull($harmony->own_voice_friends[0]['name'], 'Ben chose not to show his name.');
        $friend = collect(app(HarmonyFacts::class)->guides($harmony))->firstWhere('friend', true);
        $this->assertTrue($friend['ownVoice'] ?? false);
        $this->assertArrayHasKey('aiBasedOn', $friend);
        $this->assertNull($friend['aiBasedOn'], 'Shown as "a friend".');
        $this->assertNotContains($friend['part'], (array) $harmony->own_voice_parts, 'His parts are his, not Ann\'s.');
    }

    public function test_a_friend_only_ever_sings_parts_the_invite_named_and_sees_them_before_agreeing(): void
    {
        // Ann names the alto only; Ben's high voice would suit the soprano too.
        $this->as($this->ann);
        $made = $this->postJson("/api/own-choir/requests/{$this->song->sqid()}/invites", ['parts' => ['alto']])->assertCreated();
        $token = Str::afterLast((string) $made->json('data.url'), '/');
        $this->assertMatchesContract($this->postJson("/api/own-choir/requests/{$this->song->sqid()}/invites", ['parts' => ['descant']]), 'ownChoir.invites.create', 422);

        $this->as($this->ben);
        $this->getJson("/api/own-choir/join/{$token}?useSavedVoice=1")->assertJsonPath('data.parts', ['alto'])->assertJsonPath('data.yourParts', ['alto']);
        $this->assertMatchesContract($this->postJson("/api/own-choir/join/{$token}", ['parts' => ['soprano', 'alto'], 'showName' => false, 'useSavedVoice' => true]), 'ownChoir.invites.accept', 409);
        $this->postJson("/api/own-choir/join/{$token}", ['parts' => ['alto'], 'showName' => false, 'useSavedVoice' => true])->assertCreated()->assertJsonPath('data.parts', ['alto']);
        $this->assertSame(['alto'], OwnChoirRequest::query()->whereNotNull('parent_request_id')->sole()->only_parts);

        // Signed out, the parts can't be told yet.
        auth()->logout();
        $this->as($this->ann);
        $second = Str::afterLast((string) $this->postJson("/api/own-choir/requests/{$this->song->sqid()}/invites", ['parts' => ['soprano', 'alto']])->json('data.url'), '/');
        auth()->logout();
        $this->getJson("/api/own-choir/join/{$second}")->assertJsonPath('data.yourParts', null);
    }

    public function test_a_later_friend_never_takes_an_earlier_friends_parts(): void
    {
        $token = $this->invite();
        $this->as($this->ben);
        $this->join($token, ['showName' => false, 'useSavedVoice' => true])->assertCreated();
        $bens = OwnChoirMember::query()->sole()->parts;

        $cara = $this->singer('Cara');
        OwnChoirVoice::factory()->create(['user_id' => $cara->id]);
        UserLearning::factory()->create(['user_id' => $cara->id, 'progress' => ['range' => ['low' => 60, 'high' => 79], 'rangeSource' => 'measured']]);
        $second = $this->invite();
        $this->as($cara);
        $caras = (array) $this->getJson("/api/own-choir/join/{$second}?useSavedVoice=1")->json('data.yourParts');

        $this->assertSame([], array_intersect($bens, $caras));
        $this->assertSame($bens, OwnChoirMember::query()->where('user_id', $this->ben->id)->sole()->parts, 'Ben\'s parts never change.');
    }

    public function test_the_part_sung_along_to_tells_the_parts_when_the_range_isnt_measured(): void
    {
        $token = $this->invite();
        $dan = $this->singer('Dan');
        $this->as($dan);

        $this->getJson("/api/own-choir/join/{$token}")->assertJsonPath('data.yourParts', null);
        $this->assertContains('soprano', (array) $this->getJson("/api/own-choir/join/{$token}?partKey=soprano")->json('data.yourParts'));
    }

    public function test_a_low_friend_singing_the_tune_an_octave_down_is_given_the_low_parts(): void
    {
        // A friend sings the tune too (2026-11-23), here an octave down as most men do: the app measured -12 on the
        // take still on the device, and the yes names the low parts their voice suits, never the tune's own.
        // Ann's measured range is high here, so the low parts suit Dan better than her.
        UserLearning::factory()->create(['user_id' => $this->ann->id, 'progress' => ['range' => ['low' => 60, 'high' => 79], 'rangeSource' => 'measured']]);
        $token = $this->invite();
        $dan = $this->singer('Dan');
        $this->as($dan);

        $low = (array) $this->getJson("/api/own-choir/join/{$token}?partKey=soprano&octave=-12")->json('data.yourParts');
        $this->assertNotContains('soprano', $low);
        $this->assertNotContains('alto', $low);
        $this->assertEqualsCanonicalizing(['tenor', 'bass'], $low, 'The low parts invited for him.');
        $this->assertNotContains('bass', (array) $this->getJson("/api/own-choir/join/{$token}?partKey=soprano&octave=0")->json('data.yourParts'), 'Sung as written: not the bass.');
        $this->assertSame($this->getJson("/api/own-choir/join/{$token}?partKey=soprano")->json('data.yourParts'), $this->getJson("/api/own-choir/join/{$token}?partKey=soprano&octave=7")->json('data.yourParts'), 'Only -12, 0 or 12.');

        // He agrees to exactly those, the clip recorded as the tune with the octave the app measured.
        $wav = Wav::tone(8.0);
        $this->assertSame($low, $this->getJson("/api/own-choir/join/{$token}?partKey=bass&octave=-12")->json('data.yourParts'), 'Always the tune, whatever part is named.');
        $this->postJson("/api/own-choir/join/{$token}", ['parts' => ['soprano'], 'showName' => false, 'partKey' => 'soprano', 'octave' => -12, 'offsetFrames' => 24000, 'sampleRate' => 48000, 'frames' => intdiv(strlen($wav) - 44, 2), 'bytes' => strlen($wav), 'sha256' => hash('sha256', $wav)])->assertStatus(409)->assertJsonPath('code', 'parts_changed');
        $this->postJson("/api/own-choir/join/{$token}", ['parts' => array_values($low), 'showName' => false, 'partKey' => 'soprano', 'octave' => -12, 'offsetFrames' => 24000, 'sampleRate' => 48000, 'frames' => intdiv(strlen($wav) - 44, 2), 'bytes' => strlen($wav), 'sha256' => hash('sha256', $wav)])->assertCreated();
        $job = OwnChoirRequest::query()->where('user_id', $dan->id)->sole();
        $this->assertSame(['soprano', -12], [$job->part_key, $job->octave]);
        $this->assertSame('voice-capture', $job->clipTemplateVersion?->template?->slug, 'A friend sings the voice-capture tune too.');
        $this->assertEqualsCanonicalizing($low, (array) OwnChoirMember::query()->sole()->parts);
    }

    public function test_the_worker_reports_the_octave_and_range_sung_kept_with_the_voice_and_checked(): void
    {
        $token = $this->invite();
        $dan = $this->singer('Dan');
        $this->as($dan);
        $wav = Wav::tone(8.0);
        $this->join($token, ['showName' => false, 'partKey' => 'soprano', 'octave' => -12, 'offsetFrames' => 24000, 'sampleRate' => 48000, 'frames' => intdiv(strlen($wav) - 44, 2), 'bytes' => strlen($wav), 'sha256' => hash('sha256', $wav)])->assertCreated();
        $member = OwnChoirMember::query()->sole();
        Storage::disk('private')->put((string) OwnChoirRequest::query()->where('own_choir_member_id', $member->id)->value('upload_key'), $wav);
        $this->postJson("/api/own-choir/members/{$member->sqid()}/complete")->assertOk();

        $claim = $this->worker('POST', '/api/worker/choir/claim')->assertOk();
        $job = OwnChoirRequest::query()->findOrFail(Ids::decode((string) $claim->json('data.id')));
        $reference = Wav::tone(2.0);
        $body = ['claim' => (string) $claim->json('data.claim'), 'sampleRate' => 48000, 'frames' => intdiv(strlen($reference) - 44, 2), 'bytes' => strlen($reference), 'sha256' => hash('sha256', $reference), 'meta' => ['v' => 1]];
        $this->worker('POST', "/api/worker/choir/jobs/{$job->sqid()}/reference", $body + ['octave' => 7])->assertStatus(422);
        $this->worker('POST', "/api/worker/choir/jobs/{$job->sqid()}/reference", $body + ['sungRange' => [48, 200]])->assertStatus(422);
        $this->worker('POST', "/api/worker/choir/jobs/{$job->sqid()}/reference", $body + ['octave' => 0, 'sungRange' => [48, 65]])->assertCreated();

        $voice = OwnChoirVoice::query()->where('user_id', $dan->id)->sole();
        $this->assertSame([0, 48, 65], [$voice->octave, $voice->sung_low, $voice->sung_high]);
        $voice->forceFill(['status' => 'ready'])->save();
        $this->assertTrue(AuditLog::query()->where('action', 'own_choir.octave_differs')->exists(), 'The app heard -12, the worker 0: logged.');

        // His next invite reads the range his voice actually sang, widened two semitones.
        $this->assertSame([46, 67], ChoirTogether::range($dan->fresh() ?? $dan, null, []));
    }

    public function test_a_friend_reads_the_own_choir_terms_and_only_staff_see_the_draft_notice(): void
    {
        $terms = app(LegalText::class)->read(OwnChoir::TERMS);
        $this->as($this->ben);
        $member = $this->getJson('/api/beta/agreement?for=own_choir')->assertOk();
        $this->assertMatchesContract($member, 'beta.agreement.show', 200);
        $member->assertJsonPath('data.title', $terms['title'])->assertJsonPath('data.version', OwnChoir::termsVersion())->assertJsonPath('data.draft', false);

        $staff = User::factory()->admin()->create();
        $this->as($staff);
        $this->getJson('/api/beta/agreement?for=own_choir')->assertJsonPath('data.draft', ! $terms['reviewed']);

        // accept's consent_required means exactly these terms, at their current version.
        OwnChoirConsent::query()->where('user_id', $this->ben->id)->update(['version' => OwnChoir::termsVersion() - 1]);
        $token = $this->invite();
        $this->as($this->ben);
        $this->join($token, ['showName' => false, 'useSavedVoice' => true])->assertForbidden()->assertJsonPath('code', 'consent_required');
    }

    /** Ann shares her song with Ben's parts in it. */
    private function shareAnns(): Harmony
    {
        $this->as($this->ann);
        $project = (string) Str::uuid();
        $this->saveProject($project, 0, ['settings' => ['choir' => ['requestId' => $this->song->sqid()]]])->assertOk();
        $shared = $this->startHarmony($project, Wav::stereo(2.0))->assertCreated();

        return Harmony::query()->where('public_id', (string) $shared->json('data.id'))->firstOrFail();
    }

    public function test_a_friend_leaving_takes_down_the_shares_that_held_their_parts_and_the_owners_download_loses_them(): void
    {
        $token = $this->invite();
        $this->as($this->ben);
        $this->join($token, ['showName' => true, 'useSavedVoice' => true])->assertCreated();
        $job = $this->render();
        $share = $this->shareAnns();
        $this->assertSame('Ben', $share->own_voice_friends[0]['name'] ?? null);

        // Ann's download holds Ben's soprano while he's in it.
        $this->ann->forceFill(['store_plus_until' => now()->addDays(10)])->save();
        $this->as($this->ann);
        $benSoprano = OwnChoirPart::query()->where('own_choir_request_id', $job->id)->where('part_key', 'soprano')->sole();
        $this->assertStringContainsString(basename((string) $benSoprano->media_key), (string) collect($this->getJson("/api/own-choir/requests/{$this->song->sqid()}/download")->json('data.parts'))->firstWhere('partKey', 'soprano')['url']);
        $spent = OwnChoir::quota($this->ann)['minutesLeft'];

        $this->as($this->ben);
        $this->deleteJson('/api/own-choir/members/'.OwnChoirMember::query()->sole()->sqid())->assertNoContent();

        $this->assertNotNull($share->refresh()->deleted_at, 'The share that held his voice is down.');
        $this->as($this->ann);
        $this->assertStringNotContainsString(basename((string) $benSoprano->media_key), (string) json_encode($this->getJson("/api/own-choir/requests/{$this->song->sqid()}/download")->json('data.parts')));
        $this->assertSame($spent, OwnChoir::quota($this->ann)['minutesLeft'], 'What his parts cost still counts.');
    }

    public function test_a_friends_share_of_the_song_labels_the_owners_parts_as_hers(): void
    {
        $token = $this->invite();
        $this->as($this->ben);
        $this->join($token, ['showName' => false, 'useSavedVoice' => true])->assertCreated();
        $this->render();

        // Ben's Learn copy names Ann's song.
        $this->as($this->ben);
        $project = (string) Str::uuid();
        $this->saveProject($project, 0, ['settings' => ['choir' => ['requestId' => $this->song->sqid()]]])->assertOk();
        $shared = $this->startHarmony($project, Wav::stereo(2.0))->assertCreated();
        $harmony = Harmony::query()->where('public_id', (string) $shared->json('data.id'))->firstOrFail();

        $this->assertContains('Soprano', (array) $harmony->own_voice_parts, 'His own parts are his voice.');
        $anns = collect($harmony->own_voice_friends)->firstWhere('part', 'Bass');
        $this->assertSame(['Ann', $this->song->id], [$anns['name'] ?? null, $anns['song'] ?? null]);

        // Ann deletes her song: his share holding her voice comes down.
        $this->as($this->ann);
        $this->deleteJson("/api/own-choir/requests/{$this->song->sqid()}")->assertNoContent();
        $this->assertNotNull($harmony->refresh()->deleted_at);
    }

    public function test_the_caps_hold_under_the_lock_and_count_open_invites(): void
    {
        $this->ann->forceFill(['store_plus_until' => null])->save();
        $token = $this->invite();
        // A free song's one place is that open invite: no second.
        $this->as($this->ann);
        $this->postJson("/api/own-choir/requests/{$this->song->sqid()}/invites")->assertForbidden()->assertJsonPath('code', 'plus_required');

        // Someone took the place another way: the open link's accept is refused, not a second friend.
        OwnChoirMember::factory()->create(['own_choir_request_id' => $this->song->id, 'user_id' => $this->singer('Cara')->id, 'parts' => []]);
        $this->as($this->ben);
        $this->assertMatchesContract($this->join($token, ['showName' => false, 'useSavedVoice' => true]), 'ownChoir.invites.accept', 403);
    }

    public function test_plus_not_paid_for_waits_without_a_song_left_and_held_parts_count_once(): void
    {
        $this->ann->forceFill(['store_plus_until' => null, 'plan' => 'plus'])->save();
        config(['own_choir.comped_songs' => 1]);
        $token = $this->invite();
        $this->as($this->ben);
        $this->join($token, ['showName' => false, 'useSavedVoice' => true])->assertCreated()->assertJsonPath('data.status', 'waiting_minutes');

        // Paid Plus: a held job costs nothing until released, then once.
        $this->ann->forceFill(['plan' => 'free', 'store_plus_until' => now()->addDays(10)])->save();
        config(['own_choir.plus_minutes' => 60]);
        $job = OwnChoirRequest::query()->whereNotNull('parent_request_id')->sole();
        $before = OwnChoir::quota($this->ann->fresh() ?? $this->ann)['minutesLeft'];
        $this->assertSame(60.0, $before, 'Held: not spent yet.');
        ChoirTogether::release();
        $this->assertEqualsWithDelta(60.0 - $job->part_minutes, OwnChoir::quota($this->ann->fresh() ?? $this->ann)['minutesLeft'], 0.05);
    }

    public function test_a_closed_link_names_nothing_and_the_way_out_works_with_the_switch_off(): void
    {
        $token = $this->invite();
        $this->as($this->ben);
        $this->join($token, ['showName' => false, 'useSavedVoice' => true])->assertCreated();
        $closed = $this->getJson("/api/own-choir/join/{$token}")->assertOk();
        $this->assertMatchesContract($closed, 'ownChoir.invites.show', 200);
        $closed->assertJsonPath('data.status', 'used')->assertJsonPath('data.title', null)->assertJsonPath('data.owner', null)->assertJsonPath('data.parts', [])->assertJsonPath('data.words', null);

        app(Flags::class)->set('own_choir_together', 'off', null, 'test');
        $this->getJson('/api/own-choir')->assertJsonPath('data.joined.0.song.id', $this->song->sqid());
        $this->as($this->ann);
        $this->getJson('/api/own-choir')->assertJsonPath('data.requests.0.members.0.name', 'Ben')->assertJsonPath('data.requests.0.invite', null);
        $this->as($this->ben);
        $this->deleteJson('/api/own-choir/members/'.OwnChoirMember::query()->sole()->sqid())->assertNoContent();
    }

    public function test_an_invite_token_never_reaches_an_error_report(): void
    {
        $this->assertSame('GET /choir/join/[token] and /api/own-choir/join/[token]', ScrubEvent::mask('GET /choir/join/'.str_repeat('a', 24).' and /api/own-choir/join/'.str_repeat('B', 32)));
        $token = $this->invite();
        $this->assertSame(32, strlen($token));
    }

    public function test_a_friends_name_on_a_shared_page_passes_the_same_filters_as_anyones(): void
    {
        $token = $this->invite();
        $this->ben->forceFill(['hide_profile' => true])->save();
        $this->as($this->ben);
        $this->join($token, ['showName' => true, 'useSavedVoice' => true])->assertCreated();
        $this->render();

        $friends = (array) $this->shareAnns()->own_voice_friends;
        $this->assertArrayHasKey('name', $friends[0]);
        $this->assertNull($friends[0]['name'], 'A hidden profile is never named.');
    }

    public function test_choir_settings_mix_is_plus_and_saved_at_once(): void
    {
        $this->as($this->ben);
        $this->assertMatchesContract($this->patchJson("/api/own-choir/requests/{$this->song->sqid()}/sound", ['sound' => ['room' => 'chapel']]), 'ownChoir.requests.sound', 404);

        $this->ann->forceFill(['store_plus_until' => null])->save();
        $this->as($this->ann);
        $this->assertMatchesContract($this->patchJson("/api/own-choir/requests/{$this->song->sqid()}/sound", ['sound' => ['room' => 'chapel']]), 'ownChoir.requests.sound', 403);

        $this->ann->forceFill(['store_plus_until' => now()->addDays(10)])->save();
        $this->as($this->ann);
        $this->assertMatchesContract($this->patchJson("/api/own-choir/requests/{$this->song->sqid()}/sound", ['sound' => ['room' => 'cave']]), 'ownChoir.requests.sound', 422);
        $saved = $this->patchJson("/api/own-choir/requests/{$this->song->sqid()}/sound", ['sound' => ['room' => 'chapel', 'balance' => [['partKey' => 'bass', 'db' => -3]], 'leadBlend' => 0.5, 'recordingLevel' => null]]);
        $this->assertMatchesContract($saved, 'ownChoir.requests.sound', 200);
        $saved->assertJsonPath('data.settings.sound.room', 'chapel')->assertJsonPath('data.settings.sound.balance.3', ['partKey' => 'bass', 'db' => -3])->assertJsonPath('data.settings.sound.balance.0.db', 0);
        $this->assertSame(0, (int) OwnChoirRequest::query()->where('parent_request_id', $this->song->id)->count(), 'Free: nothing rendered.');
    }

    public function test_a_remake_makes_the_owners_parts_again_and_replaces_them_when_made(): void
    {
        OwnChoirVoice::factory()->create(['user_id' => $this->ann->id, 'status' => 'ready']);
        $this->as($this->ann);
        $sung = ['humanize' => 2, 'voicing' => 'written', 'breaths' => 'natural', 'quality' => 'fine', 'ensembleTiming' => 'loose', 'pitchSpread' => 'subtle'];
        $old = OwnChoirPart::query()->where('own_choir_request_id', $this->song->id)->pluck('id')->all();

        $made = $this->postJson("/api/own-choir/requests/{$this->song->sqid()}/remake", ['sung' => $sung]);
        $this->assertMatchesContract($made, 'ownChoir.requests.remake', 202);
        $made->assertJsonPath('data.remake.status', 'queued')->assertJsonPath('data.remake.settings.quality', 'fine');
        $this->assertMatchesContract($this->postJson("/api/own-choir/requests/{$this->song->sqid()}/remake", ['sung' => $sung]), 'ownChoir.requests.remake', 409);
        $remake = OwnChoirRequest::query()->where('parent_request_id', $this->song->id)->where('remake', true)->sole();
        $this->assertSame(round((float) $made->json('data.remakeMinutes.fine'), 1), round($remake->part_minutes, 1), 'Charged as the app was told: fine at its real cost.');
        $this->assertEqualsWithDelta($remake->part_minutes, 2 * (float) $made->json('data.remakeMinutes.standard'), 0.11);

        // The worker gets the settings, and the song plays as it is meanwhile.
        $claim = $this->worker('POST', '/api/worker/choir/claim')->assertOk();
        $claim->assertJsonPath('data.settings.humanize', 2)->assertJsonPath('data.settings.pitchSpread', 'subtle');
        $this->assertCount(4, OwnChoirPart::query()->where('own_choir_request_id', $this->song->id)->get());
        $this->worker('POST', "/api/worker/choir/jobs/{$remake->sqid()}/finish", ['failed' => 'render_failed', 'claim' => (string) $claim->json('data.claim')])->assertOk();
        $this->as($this->ann);
        $this->getJson('/api/own-choir')->assertJsonPath('data.requests.0.remake.status', 'failed');

        // Again, made this time: the new parts replace the old, and the song takes its settings.
        $this->postJson("/api/own-choir/requests/{$this->song->sqid()}/remake", ['sung' => $sung])->assertStatus(202);
        $this->render();
        $song = $this->song->refresh();
        $this->assertSame([], array_values(array_intersect($old, OwnChoirPart::query()->where('own_choir_request_id', $song->id)->pluck('id')->all())));
        $this->assertCount(4, OwnChoirPart::query()->where('own_choir_request_id', $song->id)->where('status', 'ready')->get());
        $this->assertSame('fine', $song->sung['quality'] ?? null);
        $this->as($this->ann);
        $this->getJson('/api/own-choir')->assertJsonPath('data.requests.0.remake', null)->assertJsonPath('data.requests.0.settings.sung.humanize', 2);

        // Without Plus: refused.
        $this->ann->forceFill(['store_plus_until' => null])->save();
        $this->as($this->ann);
        $this->assertMatchesContract($this->postJson("/api/own-choir/requests/{$this->song->sqid()}/remake", ['sung' => $sung]), 'ownChoir.requests.remake', 403);
    }
}
