<?php

declare(strict_types=1);

namespace Tests\Feature\Ideas;

use App\Actions\Ideas\TellWriters;
use App\Actions\Users\DeleteAccount;
use App\Mail\Ideas\IdeaVoicesMail;
use App\Models\BellItem;
use App\Models\Idea;
use App\Models\IdeaVoice;
use App\Models\Notice;
use App\Models\Report;
use App\Models\Template;
use App\Models\User;
use App\Models\UserBlock;
use App\Support\Flags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\Wav;
use Tests\TestCase;

/**
 * Ideas (docs/your-tune-plan.md, step 3; contract/api.md, "Ideas"): a tune shared with every
 * part open, voices added by anyone 18 or over, the writer told by the bell and a daily email.
 */
final class IdeasTest extends TestCase
{
    use RefreshDatabase;

    private function open(): void
    {
        app(Flags::class)->set('ideas', 'on', null, 'Opening the Ideas shelf');
    }

    /**
     * Someone 18 or over with a verified email (trust level $level), signed in.
     */
    private function person(string $name, int $level = 2): User
    {
        $user = User::factory()->create(['name' => $name, 'adult_confirmed_at' => now(), 'trust_level_override' => $level])->fresh() ?? throw new \LogicException;
        $this->actingAs($user);

        return $user;
    }

    /**
     * A template of the signed-in person's: Amazing Grace's JSON under a new id.
     */
    private function tune(string $title = 'Bus Stop Hum'): string
    {
        $id = (string) Str::uuid();
        $data = (array) json_decode((string) file_get_contents(base_path('../web/templates/amazing-grace.json')), true);
        $this->putJson('/api/templates/'.$id, ['changeId' => (string) Str::uuid(), 'template' => [...$data, 'id' => $id, 'version' => 1, 'title' => $title, 'reviewed' => true]])->assertOk();

        return $id;
    }

    /**
     * Shared and approved: on the shelf.
     */
    private function shared(string $title = 'Bus Stop Hum', int $voicing = 4): Idea
    {
        $this->postJson('/api/ideas', ['templateId' => $this->tune($title), 'note' => 'Hummed on the bus.', 'voicing' => $voicing, 'rightsConfirmed' => true])->assertCreated();
        $idea = Idea::query()->latest('id')->firstOrFail();
        $idea->forceFill(['held_at' => null])->save();

        return $idea;
    }

    /**
     * The signed-in person sings a part: started, uploaded and completed (processed at once).
     */
    private function sing(Idea $idea, string $part, float $seconds = 1.0): TestResponse
    {
        $wav = Wav::tone($seconds);
        $id = (string) Str::uuid();
        $this->postJson('/api/ideas/'.$idea->public_id.'/voices', ['id' => $id, 'partKey' => $part, 'sampleRate' => 48000, 'frames' => (int) round($seconds * 48000), 'offsetFrames' => 0, 'bytes' => strlen($wav), 'sha256' => hash('sha256', $wav), 'rightsConfirmed' => true])->assertCreated();
        Storage::disk('private')->put(IdeaVoice::query()->where('uuid', $id)->sole()->upload_key, $wav);

        return $this->postJson('/api/idea-voices/'.$id.'/complete');
    }

    public function test_an_idea_has_a_public_page_with_its_credits_while_the_shelf_lists_it(): void
    {
        $this->open();
        $this->person('Sam');
        $idea = $this->shared('Bus Stop Hum');
        $this->person('Bo');
        $this->sing($idea, 'alto')->assertOk();
        $this->app['auth']->forgetGuards();

        $page = $this->get('/ideas/'.$idea->public_id)->assertOk();
        $page->assertSee('Bus Stop Hum, a tune by Sam')->assertSee('data-part="alto"', false)->assertSee('Bo')->assertSee('idea='.$idea->public_id, false);
        // Credits only: never a voice's audio.
        $page->assertDontSee('private-media', false);
        $this->get('/sitemap.xml')->assertSee('/ideas/'.$idea->public_id);

        // Held for review, or the shelf closed: not there.
        $idea->forceFill(['held_at' => now()])->save();
        $this->get('/ideas/'.$idea->public_id)->assertNotFound();
        $this->get('/sitemap.xml')->assertDontSee('/ideas/'.$idea->public_id);
        $idea->forceFill(['held_at' => null])->save();
        app(Flags::class)->set('ideas', 'off', null, 'Closing');
        $this->get('/ideas/'.$idea->public_id)->assertNotFound();
        $this->get('/ideas/abcdefghijkl')->assertNotFound();
    }

    public function test_nothing_shows_or_shares_while_ideas_are_closed_but_mine_answers(): void
    {
        $this->person('Ana');
        $tune = $this->tune();

        $this->assertMatchesContract($this->getJson('/api/ideas'), 'ideas.list', 404);
        $closed = $this->postJson('/api/ideas', ['templateId' => $tune, 'rightsConfirmed' => true]);
        $this->assertMatchesContract($closed, 'ideas.create', 409);
        $closed->assertJsonPath('code', 'ideas_closed');
        $this->assertMatchesContract($this->getJson('/api/ideas/mine'), 'ideas.mine', 200);
        $this->assertMatchesContract($this->getJson('/api/ideas/abcdefghijkl'), 'ideas.show', 404);
    }

    public function test_a_writer_shares_a_tune_with_every_part_open_and_anyone_may_open_it(): void
    {
        $this->open();
        $teen = User::factory()->create()->fresh() ?? throw new \LogicException;
        $this->actingAs($teen);
        $theirs = $this->tune();
        $this->assertMatchesContract($this->postJson('/api/ideas', ['templateId' => $theirs, 'rightsConfirmed' => true]), 'ideas.create', 403);
        $this->actingAs(User::factory()->create(['adult_confirmed_at' => now(), 'email_verified_at' => null, 'trust_level_override' => 0])->fresh() ?? throw new \LogicException);
        $this->assertMatchesContract($this->postJson('/api/ideas', ['templateId' => $theirs, 'rightsConfirmed' => true]), 'ideas.create', 403);

        $ana = $this->person('Ana');
        $tune = $this->tune();
        $this->assertMatchesContract($this->postJson('/api/ideas', ['templateId' => $tune]), 'ideas.create', 422);
        $this->assertMatchesContract($this->postJson('/api/ideas', ['templateId' => $tune, 'voicing' => 7, 'rightsConfirmed' => true]), 'ideas.create', 422);
        $this->assertMatchesContract($this->postJson('/api/ideas', ['templateId' => $theirs, 'rightsConfirmed' => true]), 'ideas.create', 404);

        $made = $this->postJson('/api/ideas', ['templateId' => $tune, 'note' => 'Who wants the bass?', 'voicing' => 3, 'rightsConfirmed' => true]);
        $this->assertMatchesContract($made, 'ideas.create', 201);
        // Melody first, and the first two wait for review.
        $made->assertJsonPath('data.parts.0.key', 'soprano')->assertJsonPath('data.voicing', 3)->assertJsonPath('data.status', 'held')->assertJsonPath('data.writer.name', 'Ana')->assertJsonPath('data.song.grant', 'own');
        $this->assertSame(['soprano', 'alto', 'bass'], array_column((array) $made->json('data.parts'), 'key'));
        $again = $this->postJson('/api/ideas', ['templateId' => $tune, 'rightsConfirmed' => true]);
        $this->assertMatchesContract($again, 'ideas.create', 200);
        $again->assertJsonPath('data.id', $made->json('data.id'));

        // Held: not on the shelf, but its link opens.
        $id = (string) $made->json('data.id');
        $this->getJson('/api/ideas')->assertJsonCount(0, 'data');
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/ideas/'.$id)->assertOk()->assertJsonPath('data.song.grant', 'idea');
        Idea::query()->where('public_id', $id)->update(['held_at' => null]);

        $list = $this->getJson('/api/ideas');
        $this->assertMatchesContract($list, 'ideas.list', 200);
        $list->assertJsonPath('data.0.id', $id)->assertJsonPath('data.0.mine', false);
        $this->assertMatchesContract($this->getJson('/api/ideas?tab=never'), 'ideas.list', 422);
        // Its tune opens for anyone while it's listed.
        $this->getJson('/api/templates/'.$tune)->assertOk()->assertJsonPath('data.grant', 'idea');

        $this->actingAs($ana);
        $this->assertMatchesContract($this->getJson('/api/ideas/mine'), 'ideas.mine', 200);
        $this->assertMatchesContract($this->deleteJson('/api/ideas/'.$id), 'ideas.delete', 204);
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/templates/'.$tune)->assertNotFound();
    }

    public function test_singers_add_voices_the_writer_hears_and_it_completes(): void
    {
        $this->open();
        $ana = $this->person('Ana', 3);
        $idea = $this->shared(voicing: 2);

        // The writer's own voice on the melody.
        $this->sing($idea, 'soprano')->assertOk();

        $sam = $this->person('Sam');
        $refused = $this->postJson('/api/ideas/'.$idea->public_id.'/voices', ['id' => (string) Str::uuid(), 'partKey' => 'descant', 'sampleRate' => 48000, 'frames' => 48000, 'offsetFrames' => 0, 'bytes' => 96044, 'sha256' => str_repeat('a', 64), 'rightsConfirmed' => true]);
        $this->assertMatchesContract($refused, 'ideas.voices.create', 422);
        $this->assertMatchesContract($this->postJson('/api/ideas/abcdefghijkl/voices', ['id' => (string) Str::uuid(), 'partKey' => 'alto', 'sampleRate' => 48000, 'frames' => 48000, 'offsetFrames' => 0, 'bytes' => 96044, 'sha256' => str_repeat('a', 64), 'rightsConfirmed' => true]), 'ideas.voices.create', 404);
        $this->assertSame([$idea->public_id], array_column((array) $this->getJson('/api/ideas?needs=alto')->json('data'), 'id'));

        $done = $this->sing($idea, 'alto');
        $this->assertMatchesContract($done, 'ideas.voices.complete', 200);
        $done->assertJsonPath('data.status', 'ready')->assertJsonPath('data.mine', true)->assertJsonPath('data.framesPerPeak', 512);
        $this->assertSame([], array_column((array) $this->getJson('/api/ideas?needs=alto')->json('data'), 'id'));

        $shown = $this->getJson('/api/ideas/'.$idea->public_id);
        $this->assertMatchesContract($shown, 'ideas.show', 200);
        $shown->assertJsonPath('data.complete', true)->assertJsonPath('data.voices', 2)->assertJsonCount(2, 'data.voiceList')->assertJsonPath('data.voiceList.0.singer.name', 'Sam');
        $this->assertNotNull($shown->json('data.voiceList.0.url'));
        $this->assertSame([$idea->public_id], array_column((array) $this->getJson('/api/ideas?tab=complete')->json('data'), 'id'));

        // The bell: Sam's voice (never Ana's own), and complete once.
        $this->assertSame(['idea_complete', 'idea_voice'], BellItem::query()->where('user_id', $ana->id)->orderBy('kind')->pluck('kind')->all());
        $this->actingAs($ana);
        $this->getJson('/api/notifications')->assertJsonPath('data.0.idea.id', $idea->public_id);

        // The email: once, with Sam's voice and that it's complete, unsubscribable in one click.
        Mail::fake();
        $this->artisan('app:ideas:notify')->expectsOutputToContain('Emailed 1')->assertSuccessful();
        Mail::assertQueued(IdeaVoicesMail::class, function (IdeaVoicesMail $mail) use ($ana): bool {
            return $mail->hasTo($ana->email) && $mail->complete && $mail->voices === [['name' => 'Sam', 'part' => 'alto', 'played' => false]];
        });
        $this->artisan('app:ideas:notify')->expectsOutputToContain('Emailed 0')->assertSuccessful();

        // Another voice the same day waits a day, and none at all once they're turned off.
        $this->actingAs($this->person('Kim'));
        $this->sing($idea, 'alto')->assertOk();
        $this->artisan('app:ideas:notify')->expectsOutputToContain('Emailed 0');
        $this->travel(25)->hours();
        $this->post(TellWriters::unsubscribeUrl($ana))->assertOk()->assertSee(__('ideas.unsubscribe.done_title'));
        $this->assertFalse($ana->refresh()->idea_emails);
        $this->artisan('app:ideas:notify')->expectsOutputToContain('Emailed 0');
        Mail::assertQueuedCount(1);

        // A singer deletes their voice; the writer may delete any.
        $this->actingAs(User::query()->where('name', 'Sam')->sole());
        $sams = IdeaVoice::query()->where('user_id', User::query()->where('name', 'Sam')->value('id'))->sole();
        $this->assertMatchesContract($this->deleteJson('/api/idea-voices/'.$sams->uuid), 'ideas.voices.delete', 204);
        Storage::disk('private')->assertMissing((string) $sams->file_key);
        $this->assertSame(2, $idea->refresh()->voices_count);
    }

    public function test_a_singers_new_voice_replaces_theirs_and_accounts_take_their_voices_and_ideas(): void
    {
        $this->open();
        $ana = $this->person('Ana', 3);
        $idea = $this->shared();
        $sam = $this->person('Sam');
        $this->sing($idea, 'alto')->assertOk();
        $first = IdeaVoice::query()->sole();
        $this->sing($idea, 'alto', 2.0)->assertOk();
        $this->assertNull($first->fresh());
        Storage::disk('private')->assertMissing((string) $first->file_key);
        $this->assertSame(1, $idea->refresh()->voices_count);

        app(DeleteAccount::class)->handle($sam, null);
        $this->assertSame(0, IdeaVoice::query()->count());
        $this->assertSame(0, $idea->refresh()->voices_count);

        $this->actingAs($ana);
        $this->sing($idea, 'soprano')->assertOk();
        $file = (string) IdeaVoice::query()->sole()->file_key;
        app(DeleteAccount::class)->handle($ana, null);
        $this->assertSame(0, Idea::query()->count());
        Storage::disk('private')->assertMissing($file);
    }

    public function test_reports_hold_an_idea_and_hillel_hides_an_idea_or_one_voice(): void
    {
        $this->open();
        $ana = $this->person('Ana');
        $idea = $this->shared();
        $sam = $this->person('Sam');
        $this->sing($idea, 'alto')->assertOk();
        $voice = IdeaVoice::query()->sole();

        // Three people's reports (one signed in and verified): held for review.
        $this->app['auth']->forgetGuards();
        foreach (['203.0.113.1', '203.0.113.2'] as $ip) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip])->postJson('/api/reports', ['ideaId' => $idea->public_id, 'reason' => 'offensive'])->assertNoContent();
        }
        $this->actingAs(User::factory()->create()->fresh() ?? throw new \LogicException);
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.3'])->postJson('/api/reports', ['ideaId' => $idea->public_id, 'reason' => 'offensive'])->assertNoContent();
        $this->assertNotNull($idea->refresh()->held_at);

        $admin = $this->actingAsAdmin();
        $held = $this->getJson('/api/admin/ideas/held');
        $this->assertMatchesContract($held, 'admin.ideas.held', 200);
        $held->assertJsonPath('data.0.reports.count', 3);
        $approved = $this->postJson('/api/admin/ideas/'.$idea->public_id, ['decision' => 'approve']);
        $this->assertMatchesContract($approved, 'admin.ideas.decide', 200);
        $this->assertSame(3, Report::query()->where('status', Report::DISMISSED)->count());
        $this->assertMatchesContract($this->postJson('/api/admin/ideas/'.$idea->public_id, ['decision' => 'approve']), 'admin.ideas.decide', 409);
        $this->assertMatchesContract($this->postJson('/api/admin/ideas/'.$idea->public_id, ['decision' => 'hide']), 'admin.ideas.decide', 422);
        $this->assertMatchesContract($this->postJson('/api/admin/ideas/abcdefghijkl', ['decision' => 'approve']), 'admin.ideas.decide', 404);

        // One voice hidden: its singer is told why, and it leaves the count.
        $this->postJson('/api/admin/ideas/'.$idea->public_id, ['decision' => 'hide', 'note' => 'Shouting over the tune.', 'voiceId' => $voice->uuid])->assertOk();
        $this->assertSame(Notice::IDEA_VOICE_HIDDEN, Notice::query()->where('user_id', $sam->id)->value('kind'));
        $this->assertSame(0, $idea->refresh()->voices_count);

        // The idea hidden: off the shelf and closed to all but its writer.
        $this->postJson('/api/admin/ideas/'.$idea->public_id, ['decision' => 'hide', 'note' => 'Not a tune.'])->assertOk();
        $this->assertSame(Notice::IDEA_HIDDEN, Notice::query()->where('user_id', $ana->id)->value('kind'));
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/ideas/'.$idea->public_id)->assertNotFound();
        $this->actingAs($ana);
        $this->getJson('/api/ideas/'.$idea->public_id)->assertOk()->assertJsonPath('data.status', 'hidden');
        $this->actingAsAdmin($admin);
        $this->postJson('/api/admin/ideas/'.$idea->public_id, ['decision' => 'show', 'note' => 'Appeal upheld.'])->assertOk();
        $this->assertNull($idea->refresh()->hidden_at);
    }

    public function test_moderation_holds_through_hiding_the_tune_resinging_and_sharing_again(): void
    {
        $this->open();
        $ana = $this->person('Ana', 3);
        // Plus: more than one tune of her own.
        $ana->forceFill(['plan' => 'plus'])->save();
        $idea = $this->shared();
        $sam = $this->person('Sam');
        $this->sing($idea, 'alto')->assertOk();
        $voice = IdeaVoice::query()->sole();

        // Sam's voice hidden; singing again keeps the new one out of sight too.
        $admin = $this->actingAsAdmin();
        $this->postJson('/api/admin/ideas/'.$idea->public_id, ['decision' => 'hide', 'note' => 'Shouting.', 'voiceId' => $voice->uuid])->assertOk();
        $this->actingAs($sam);
        $this->sing($idea, 'alto')->assertOk();
        $this->assertNotNull($voice->fresh());
        $this->assertSame(0, IdeaVoice::query()->whereNull('hidden_at')->count());
        $this->assertSame(0, $idea->refresh()->voices_count);

        // Blocked with Kim: Kim's voice isn't on Sam's view of the idea.
        $kim = $this->person('Kim');
        $this->sing($idea, 'tenor')->assertOk();
        UserBlock::query()->create(['blocker_id' => $sam->id, 'blocked_id' => $kim->id]);
        $this->actingAs($sam);
        $this->assertNotContains('tenor', array_column((array) $this->getJson('/api/ideas/'.$idea->public_id)->json('data.voiceList'), 'partKey'));

        // Its tune hidden in the library: the idea is closed to everyone but Ana.
        Template::query()->whereKey($idea->template_id)->update(['hidden_at' => now()]);
        $this->getJson('/api/ideas')->assertJsonCount(0, 'data');
        $this->getJson('/api/ideas/'.$idea->public_id)->assertNotFound();
        $this->actingAs($ana);
        $this->getJson('/api/ideas/'.$idea->public_id)->assertOk();
        $tune = (string) Template::query()->whereKey($idea->template_id)->value('slug');
        $this->deleteJson('/api/ideas/'.$idea->public_id)->assertNoContent();
        $this->postJson('/api/ideas', ['templateId' => $tune, 'rightsConfirmed' => true])->assertNotFound();

        // An idea hidden, deleted and shared again waits for review, even from a trusted writer.
        $other = $this->shared('Second Tune');
        $this->actingAsAdmin($admin);
        $this->postJson('/api/admin/ideas/'.$other->public_id, ['decision' => 'hide', 'note' => 'Not a tune.'])->assertOk();
        $this->actingAs($ana);
        $again = (string) Template::query()->whereKey($other->template_id)->value('slug');
        $this->deleteJson('/api/ideas/'.$other->public_id)->assertNoContent();
        $this->postJson('/api/ideas', ['templateId' => $again, 'rightsConfirmed' => true])->assertCreated()->assertJsonPath('data.status', 'held');
    }

    public function test_the_writer_is_told_it_is_complete_once(): void
    {
        $this->open();
        $ana = $this->person('Ana', 3);
        $idea = $this->shared(voicing: 2);
        $this->sing($idea, 'soprano')->assertOk();
        $this->person('Sam');
        $this->sing($idea, 'alto')->assertOk();
        $sams = IdeaVoice::query()->where('part_key', 'alto')->sole();

        // The alto goes and comes back: complete again, but no second bell or email.
        $this->deleteJson('/api/idea-voices/'.$sams->uuid)->assertNoContent();
        $this->assertFalse((bool) $this->getJson('/api/ideas/'.$idea->public_id)->json('data.complete'));
        $this->sing($idea, 'alto')->assertOk();
        $this->assertSame(1, BellItem::query()->where('user_id', $ana->id)->where('kind', 'idea_complete')->count());

        Mail::fake();
        $this->artisan('app:ideas:notify')->assertSuccessful();
        $this->travel(25)->hours();
        $this->actingAs($this->person('Kim'));
        $this->sing($idea, 'alto')->assertOk();
        $this->artisan('app:ideas:notify')->assertSuccessful();
        Mail::assertQueued(IdeaVoicesMail::class, 2);
        $this->assertSame(1, collect(Mail::queued(IdeaVoicesMail::class))->filter(fn (IdeaVoicesMail $m): bool => $m->complete)->count());
    }
}
