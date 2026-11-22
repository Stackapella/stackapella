<?php

declare(strict_types=1);

namespace Tests\Feature\Sharing;

use App\Models\AiVoice;
use App\Models\Harmony;
use App\Models\TemplateGuide;
use App\Models\User;
use App\Support\Flags;
use App\Support\Sharing\HarmonyFiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BetaFixtures;
use Tests\TestCase;

/**
 * A shared harmony's page names an AI part in the app's Share words (design rules, "AI: plain about it, never loud"):
 * ":part: AI voice", its singer named only when they chose a credit, a real voice by its singer, and one link to About
 * AI voices.
 */
final class SharedAiPartsPageTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    public function test_the_share_page_names_ai_parts_as_ai_once_with_one_link_to_about_ai_voices(): void
    {
        app(Flags::class)->set('ai_voices', 'on', null, 'AI voices');
        $template = $this->catalogue();
        $credited = TemplateGuide::factory()->ai()->create(['template_version_id' => $template->current_version_id, 'part_key' => 'alto']);
        $plain = TemplateGuide::factory()->ai(AiVoice::factory()->consented()->create(['based_on' => 'Bo B.', 'credited' => false]))->create(['template_version_id' => $template->current_version_id, 'part_key' => 'tenor']);
        // As harmonies.create keeps them: the AI parts recorded on the share itself.
        $harmony = Harmony::factory()->create(['project_id' => null, 'template_version_id' => $template->current_version_id, 'guide_ids' => [$credited->id, $plain->id], 'ai_parts' => [['part' => $credited->part_key, 'voice' => $credited->ai_voice_id], ['part' => $plain->part_key, 'voice' => $plain->ai_voice_id]]]);
        Storage::disk('private')->put((string) $harmony->audio_key, 'aac');
        Storage::disk('private')->put((string) $harmony->card_key, 'jpg');
        app(HarmonyFiles::class)->sync($harmony);

        $html = (string) $this->get('/h/'.$harmony->refresh()->public_id)->assertOk()->getContent();

        $this->assertStringContainsString(e(__('pages.song.ai.mix_based_on', ['part' => 'Alto', 'name' => 'Ada E.'])), $html);
        $this->assertStringContainsString(e(__('pages.song.ai.mix', ['part' => 'Tenor'])), $html);
        $this->assertStringNotContainsString('Bo B.', $html);
        $this->assertStringNotContainsString(' (Alto)', $html);
        $this->assertSame(1, substr_count($html, 'href="/about/ai-voices"'));
    }

    public function test_a_choir_songs_share_page_names_its_parts_as_ai_made_from_the_sharers_singing(): void
    {
        $template = $this->catalogue();
        $sharer = User::factory()->create(['name' => 'Sam Rivers']);
        $harmony = Harmony::factory()->create(['user_id' => $sharer->id, 'project_id' => null, 'template_version_id' => $template->current_version_id, 'guide_ids' => [], 'own_voice_parts' => ['Alto', 'Bass']]);
        Storage::disk('private')->put((string) $harmony->audio_key, 'aac');
        Storage::disk('private')->put((string) $harmony->card_key, 'jpg');
        app(HarmonyFiles::class)->sync($harmony);

        $html = (string) $this->get('/h/'.$harmony->refresh()->public_id)->assertOk()->getContent();

        $this->assertStringContainsString(e(__('pages.song.ai.mix_own', ['part' => 'Alto', 'name' => 'Sam Rivers'])), $html);
        $this->assertStringContainsString(e(__('pages.song.ai.mix_own', ['part' => 'Bass', 'name' => 'Sam Rivers'])), $html);
        $this->assertSame(1, substr_count($html, 'href="/about/ai-voices"'));
    }

    public function test_a_share_with_their_real_voice_in_the_lead_says_so_once_in_place_of_the_ai_line(): void
    {
        $template = $this->catalogue();
        $sharer = User::factory()->create(['name' => 'Sam Rivers']);
        $harmony = Harmony::factory()->create(['user_id' => $sharer->id, 'project_id' => null, 'template_version_id' => $template->current_version_id, 'guide_ids' => [], 'own_voice_parts' => ['Alto', 'Bass'], 'real_voice' => ['take' => 41]]);
        Storage::disk('private')->put((string) $harmony->audio_key, 'aac');
        Storage::disk('private')->put((string) $harmony->card_key, 'jpg');
        app(HarmonyFiles::class)->sync($harmony);

        $html = (string) $this->get('/h/'.$harmony->refresh()->public_id)->assertOk()->getContent();

        $this->assertStringContainsString('data-real-voice="lead"', $html);
        $this->assertStringContainsString(e(__('sharing.page.real_lead', ['name' => 'Sam Rivers'])), $html);
        $this->assertStringNotContainsString(e(__('pages.song.ai.mix_own', ['part' => 'Alto', 'name' => 'Sam Rivers'])), $html);
        $this->assertSame(1, substr_count($html, 'href="/about/ai-voices"'));
    }
}
