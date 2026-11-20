<?php

declare(strict_types=1);

namespace Tests\Feature\AiVoices;

use App\Actions\Templates\CarryVoicesOver;
use App\Enums\GuideSource;
use App\Models\AiVoice;
use App\Models\FlagUser;
use App\Models\Template;
use App\Models\TemplateGuide;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Support\AiVoices\AiGuides;
use App\Support\Flags;
use App\Support\Pages\SongParts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BetaFixtures;
use Tests\TestCase;

/**
 * AI guide voices in the app's and the site's answers (docs/ai-voices-build-plan.md, phase 1): only with ai_voices on
 * for the person asking and the voice's consent recorded; labelled; never counted as a real voice; retired by a real
 * one; never carried to a new version.
 */
final class AiGuidesTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    private function aiAlto(Template $template, ?AiVoice $voice = null): TemplateGuide
    {
        $guide = TemplateGuide::factory()->ai($voice)->create(['template_version_id' => $template->current_version_id, 'part_key' => 'alto']);
        Storage::disk('media')->put($guide->media_key, 'aac');

        return $guide;
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogueAlto(): array
    {
        $songs = collect($this->getJson('/api/templates')->assertOk()->json('data'))->keyBy('id');

        return collect($songs['amazing-grace']['parts'])->firstWhere('key', 'alto');
    }

    private function switchOn(): void
    {
        app(Flags::class)->set('ai_voices', 'on', null, 'Testing AI voices');
    }

    public function test_switched_off_no_ai_guide_reaches_the_app_or_the_site(): void
    {
        $template = $this->catalogue();
        $this->aiAlto($template);

        $alto = $this->catalogueAlto();
        $this->assertNull($alto['guide']);
        $this->assertSame([], $alto['versions']);
        $this->assertFalse($alto['hasRealGuide']);
        $this->getJson('/api/templates/amazing-grace')->assertOk()->assertJsonCount(0, 'data.voices');
        $this->assertSame(['realVoice' => false, 'credit' => null, 'creditHandle' => null, 'source' => null, 'aiBasedOn' => null], app(SongParts::class)->guides($template)['alto']);
        $this->getJson('/api/auth/config')->assertJsonPath('data.aiVoices', false);
    }

    public function test_switched_on_an_ai_guide_is_the_parts_labelled_default_and_the_part_is_still_wanted(): void
    {
        $template = $this->catalogue();
        $guide = $this->aiAlto($template);
        $this->switchOn();

        $catalogue = $this->getJson('/api/templates');
        $this->assertMatchesContract($catalogue, 'templates.catalogue', 200);
        $alto = collect(collect($catalogue->json('data'))->firstWhere('id', 'amazing-grace')['parts'])->firstWhere('key', 'alto');
        $this->assertSame($guide->sqid(), $alto['guide']['id']);
        $this->assertSame('ai', $alto['guide']['source']);
        $this->assertSame('Ada E.', $alto['guide']['aiBasedOn']);
        $this->assertNull($alto['guide']['credit']);
        $this->assertTrue($alto['versions'][0]['default']);
        $this->assertFalse($alto['hasRealGuide'], 'An AI voice is not a real one.');
        $this->assertTrue(collect($catalogue->json('data'))->firstWhere('id', 'amazing-grace')['wantsVoice']);

        $show = $this->getJson('/api/templates/amazing-grace');
        $this->assertMatchesContract($show, 'templates.show', 200);
        $show->assertJsonPath('data.voices.0.source', 'ai');

        $this->assertSame(['realVoice' => false, 'credit' => null, 'creditHandle' => null, 'source' => 'ai', 'aiBasedOn' => 'Ada E.'], app(SongParts::class)->guides($template)['alto']);
        $page = app(SongParts::class)->page('amazing-grace', 'alto');
        $this->assertNotNull($page);
        $this->assertFalse($page['hasRealGuide']);
        $this->assertSame('ai', $page['guide']['source']);
        $this->assertTrue(SongParts::aiVoices());

        $this->assertMatchesContract($this->getJson('/api/auth/config'), 'auth.config', 200);
        $this->getJson('/api/auth/config')->assertJsonPath('data.aiVoices', true);
    }

    public function test_a_voice_without_consent_or_withdrawn_never_leaves_admin(): void
    {
        $template = $this->catalogue();
        $this->switchOn();
        $this->aiAlto($template, AiVoice::factory()->create());

        $this->assertNull($this->catalogueAlto()['guide'], 'No consent recorded yet.');

        TemplateGuide::withAi()->delete();
        $this->aiAlto($template, AiVoice::factory()->consented()->withdrawn()->create());
        $this->assertNull($this->catalogueAlto()['guide'], 'Withdrawn.');
    }

    public function test_a_voice_not_based_on_a_named_person_is_labelled_without_a_name(): void
    {
        $template = $this->catalogue();
        $this->switchOn();
        $this->aiAlto($template, AiVoice::factory()->consented()->create(['based_on' => null]));

        $guide = $this->catalogueAlto()['guide'];
        $this->assertSame('ai', $guide['source']);
        $this->assertNull($guide['aiBasedOn']);
    }

    public function test_a_singer_who_chose_no_credit_gets_the_plain_ai_label(): void
    {
        $template = $this->catalogue();
        $this->switchOn();
        $this->aiAlto($template, AiVoice::factory()->consented()->create(['based_on' => 'Bo B.', 'credited' => false]));

        $guide = $this->catalogueAlto()['guide'];
        $this->assertSame('ai', $guide['source']);
        $this->assertNull($guide['aiBasedOn']);
    }

    public function test_the_switch_can_be_on_for_one_account_first(): void
    {
        $template = $this->catalogue();
        $this->aiAlto($template);
        $user = User::factory()->create();
        FlagUser::query()->forceCreate(['flag' => 'ai_voices', 'user_id' => $user->id]);

        $this->assertNull($this->catalogueAlto()['guide'], 'Signed out it is still off.');
        $this->actingAs($user);
        $this->assertSame('ai', $this->catalogueAlto()['guide']['source']);
        $this->getJson('/api/auth/config')->assertJsonPath('data.aiVoices', true);
        $this->assertFalse(AiGuides::on(null));
    }

    public function test_the_recorded_scope_keeps_ai_guides_out_of_every_count_of_real_voices(): void
    {
        $template = $this->catalogue();
        $this->aiAlto($template);

        $this->assertSame(0, TemplateGuide::query()->count());
        $this->assertSame(1, TemplateGuide::withAi()->count());
        $this->assertSame(GuideSource::Ai, TemplateGuide::withAi()->firstOrFail()->source);
    }

    public function test_a_real_voice_retires_the_parts_ai_guide_at_once_and_counts_it(): void
    {
        $template = $this->catalogue();
        $ai = $this->aiAlto($template);
        $this->switchOn();

        DB::transaction(function () use ($template): void {
            // As every path that publishes a real voice does: the AI guide first, as it holds the part's one default.
            $this->assertSame(1, AiGuides::retireForPart((int) $template->current_version_id, 'alto'));
            TemplateGuide::factory()->create(['template_version_id' => $template->current_version_id, 'part_key' => 'alto', 'is_default' => true]);
        });

        $this->assertNull(TemplateGuide::withAi()->find($ai->id));
        Storage::disk('media')->assertMissing($ai->media_key);
        $this->assertSame(1, (int) DB::table('usage_counts')->where('kind', 'ai_replaced')->sum('count'));
        $this->assertSame('recorded', $this->catalogueAlto()['guide']['source']);
        $this->assertTrue($this->catalogueAlto()['hasRealGuide']);
    }

    public function test_a_real_voice_carried_to_a_new_version_retires_its_ai_guide_and_ai_guides_are_never_carried(): void
    {
        $template = $this->catalogue();
        $old = TemplateVersion::query()->findOrFail($template->current_version_id);
        // The old version: a recorded alto and an AI tenor.
        TemplateGuide::factory()->create(['template_version_id' => $old->id, 'part_key' => 'alto', 'is_default' => true]);
        $aiTenor = TemplateGuide::factory()->ai()->create(['template_version_id' => $old->id, 'part_key' => 'tenor']);
        // A new version with the same music, and an AI alto made for it.
        $new = TemplateVersion::factory()->create(['template_id' => $template->id, 'version' => (int) TemplateVersion::query()->where('template_id', $template->id)->max('version') + 1, 'data' => $old->data, 'reviewed' => true]);
        $template->forceFill(['current_version_id' => $new->id])->save();
        $aiAlto = TemplateGuide::factory()->ai()->create(['template_version_id' => $new->id, 'part_key' => 'alto']);

        app(CarryVoicesOver::class)->handle();

        $this->assertSame($new->id, TemplateGuide::query()->where('part_key', 'alto')->value('template_version_id'), 'The recorded alto moves over.');
        $this->assertNull(TemplateGuide::withAi()->find($aiAlto->id), 'and retires the AI alto there.');
        $this->assertSame($old->id, TemplateGuide::withAi()->findOrFail($aiTenor->id)->template_version_id, 'The AI tenor stays on its version.');
    }
}
