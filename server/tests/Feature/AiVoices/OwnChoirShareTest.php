<?php

declare(strict_types=1);

namespace Tests\Feature\AiVoices;

use App\Enums\OwnChoirStatus;
use App\Models\Harmony;
use App\Models\OwnChoirPart;
use App\Models\OwnChoirRequest;
use App\Models\Template;
use App\Models\TemplateGuide;
use App\Models\User;
use App\Support\Flags;
use App\Support\Sharing\HarmonyFacts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\BetaFixtures;
use Tests\Support\Sharing;
use Tests\Support\Wav;
use Tests\TestCase;

/**
 * Sharing a song made with Hear yourself as a choir: its own AI parts count as something to share, and the page credits
 * them as AI in the sharer's own voice, even after the choir song is deleted.
 */
final class OwnChoirShareTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;
    use Sharing;

    public function test_a_choir_song_with_no_take_is_shared_and_its_parts_credited_as_ai(): void
    {
        $this->catalogue();
        $user = $this->owner();
        [$project] = $this->projectWithPart();
        $version = (int) Template::query()->where('slug', 'amazing-grace')->value('current_version_id');
        $song = OwnChoirRequest::factory()->create(['user_id' => $user->id, 'template_version_id' => $version, 'status' => OwnChoirStatus::Ready]);
        $bass = OwnChoirPart::factory()->create(['own_choir_request_id' => $song->id, 'part_key' => 'bass', 'frames' => 48000 * 3]);
        $others = OwnChoirPart::factory()->create(['own_choir_request_id' => OwnChoirRequest::factory()->create(['user_id' => User::factory()->create()->id, 'template_version_id' => $version, 'status' => OwnChoirStatus::Ready])->id, 'part_key' => 'alto']);
        $wav = Wav::stereo(2.0);

        $this->startHarmony($project, $wav)->assertStatus(409)->assertJsonPath('code', 'no_takes');
        $this->startHarmony($project, $wav, ['choirParts' => [$others->sqid()]])->assertStatus(409)->assertJsonPath('code', 'no_takes');

        $shared = $this->startHarmony($project, $wav, ['choirParts' => [$bass->sqid(), $others->sqid()]]);
        $this->assertMatchesContract($shared, 'harmonies.create', 201);
        $harmony = Harmony::query()->where('public_id', (string) $shared->json('data.id'))->firstOrFail();
        $this->assertSame(['Bass'], $harmony->own_voice_parts);

        $credit = ['name' => null, 'part' => 'Bass', 'ai' => true, 'aiBasedOn' => $user->name, 'ownVoice' => true];
        $this->assertSame([$credit], app(HarmonyFacts::class)->guides($harmony));

        // Deleting the choir song keeps the share's label: it's on the share itself.
        $this->deleteJson('/api/own-choir')->assertNoContent();
        $this->assertNull(OwnChoirPart::query()->find($bass->id));
        $this->assertSame([$credit], app(HarmonyFacts::class)->guides($harmony->refresh()));
    }

    public function test_the_label_comes_from_the_project_and_the_song_whatever_the_client_sends(): void
    {
        $this->catalogue();
        $user = $this->owner();
        $version = (int) Template::query()->where('slug', 'amazing-grace')->value('current_version_id');
        $song = OwnChoirRequest::factory()->create(['user_id' => $user->id, 'template_version_id' => $version, 'status' => OwnChoirStatus::Ready]);
        OwnChoirPart::factory()->create(['own_choir_request_id' => $song->id, 'part_key' => 'bass', 'frames' => 48000 * 3]);
        // The project records its choir song itself (the app's settings.choir), and the client names no part.
        $project = (string) Str::uuid();
        $this->saveProject($project, 0, ['settings' => ['choir' => ['requestId' => $song->sqid()]]])->assertOk();
        // And the song has an approved AI tenor this person can hear, with no take of theirs on it.
        $tenor = TemplateGuide::factory()->ai()->create(['template_version_id' => $version, 'part_key' => 'tenor']);
        app(Flags::class)->set('ai_voices', 'on', null, 'Testing');

        $shared = $this->startHarmony($project, Wav::stereo(2.0));
        $shared->assertCreated();
        $harmony = Harmony::query()->where('public_id', (string) $shared->json('data.id'))->firstOrFail();

        $this->assertSame(['Bass'], $harmony->own_voice_parts);
        $this->assertSame([['part' => 'tenor', 'voice' => $tenor->ai_voice_id]], $harmony->ai_parts);
    }
}
