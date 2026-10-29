<?php

declare(strict_types=1);

namespace Tests\Feature\Beta;

use App\Enums\CreditMode;
use App\Http\Resources\GuideRecording;
use App\Models\BetaSinger;
use App\Models\TemplateGuide;
use App\Models\User;
use App\Support\Flags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BetaFixtures;
use Tests\TestCase;

/**
 * A guide's credit links to the singer's profile (`creditHandle`) only when they're credited by
 * name, chose to link it, have a public profile, and profiles are open (the gallery).
 */
final class CreditLinksTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    public function test_a_credit_links_to_a_public_profile_and_never_otherwise(): void
    {
        app(Flags::class)->set('gallery', 'on', null, 'Opening the gallery');
        $public = static fn (User $user, string $handle): bool => $user->forceFill(['handle' => $handle, 'adult_confirmed_at' => now(), 'public_profile_at' => now()])->save();
        $singer = BetaSinger::factory()->accepted()->consented()->create();
        $singer->load('user');

        // Credited by name, but no public profile: the name alone.
        $this->assertSame('Sarah K.', $singer->credit());
        $this->assertNull($singer->creditHandle());

        // A public profile, but they haven't chosen to link it.
        $public($singer->user, 'sarahk');
        $this->assertNull($singer->refresh()->load('user')->creditHandle());

        $singer->forceFill(['credit_links_profile' => true])->save();
        $this->assertSame('sarahk', $singer->refresh()->load('user')->creditHandle());

        // Profiles closed (the gallery off): the name alone.
        app(Flags::class)->set('gallery', 'off', null, 'Closing the gallery');
        $this->assertNull($singer->refresh()->load('user')->creditHandle());
        app(Flags::class)->set('gallery', 'on', null, 'Opening the gallery');

        // Anonymous: neither the name nor the link.
        $singer->forceFill(['credit_mode' => CreditMode::Anonymous])->save();
        $this->assertNull($singer->refresh()->load('user')->creditHandle());

        // A public song's voice that became a guide: its singer's profile, while it's public.
        $voice = User::factory()->create(['name' => 'Ana']);
        $guide = TemplateGuide::factory()->create(['credit_user_id' => $voice->id]);
        $this->assertNull(GuideRecording::of($guide->load('creditUser'))['creditHandle']);
        $public($voice, 'ana');
        $this->assertSame('ana', GuideRecording::of($guide->refresh()->load('creditUser'))['creditHandle']);
    }

    public function test_a_singer_chooses_to_link_their_credit_and_sees_whether_it_can(): void
    {
        $singer = BetaSinger::factory()->consented()->create();

        $shown = $this->asSinger($singer)->getJson('/api/beta/me');
        $this->assertMatchesContract($shown, 'beta.me.show', 200);
        $shown->assertJsonPath('data.creditLinksProfile', false)->assertJsonPath('data.hasPublicProfile', false)->assertJsonPath('data.profilesOpen', false);

        // The link alone, then the mode alone: each keeps the other.
        $linked = $this->asSinger($singer)->putJson('/api/beta/credit', ['creditLinksProfile' => true]);
        $this->assertMatchesContract($linked, 'beta.credit.update', 200);
        $linked->assertJsonPath('data.creditLinksProfile', true)->assertJsonPath('data.credit.mode', 'name');
        $this->asSinger($singer)->putJson('/api/beta/credit', ['creditMode' => 'anonymous'])->assertOk()->assertJsonPath('data.creditLinksProfile', true);

        User::query()->whereKey($singer->user_id)->update(['handle' => 'sarahk', 'adult_confirmed_at' => now(), 'public_profile_at' => now()]);
        app(Flags::class)->set('gallery', 'on', null, 'Opening the gallery');
        $this->asSinger($singer)->getJson('/api/beta/me')->assertJsonPath('data.hasPublicProfile', true)->assertJsonPath('data.profilesOpen', true);

        $this->assertMatchesContract($this->asSinger($singer)->putJson('/api/beta/credit', []), 'beta.credit.update', 422);
        $this->assertMatchesContract($this->asSinger($singer)->putJson('/api/beta/credit', ['creditLinksProfile' => 'yes']), 'beta.credit.update', 422);
    }
}
