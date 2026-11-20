<?php

declare(strict_types=1);

namespace Tests\Feature\AiVoices;

use App\Models\AiVoice;
use App\Models\Harmony;
use App\Models\TemplateGuide;
use App\Support\Sharing\HarmonyFacts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BetaFixtures;
use Tests\TestCase;

/**
 * A shared harmony with an AI part says so on its page (design rule "AI: plain about it, never loud"): the facts for the
 * view to word, its singer named only when they chose a credit.
 */
final class SharedAiCreditsTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    public function test_a_shares_ai_parts_are_credited_as_ai(): void
    {
        $template = $this->catalogue();
        $credited = TemplateGuide::factory()->ai()->create(['template_version_id' => $template->current_version_id, 'part_key' => 'alto']);
        $plain = TemplateGuide::factory()->ai(AiVoice::factory()->consented()->create(['based_on' => 'Bo B.', 'credited' => false]))->create(['template_version_id' => $template->current_version_id, 'part_key' => 'tenor']);
        // As harmonies.create keeps them: the AI parts on the share itself.
        $harmony = Harmony::factory()->create(['template_version_id' => $template->current_version_id, 'guide_ids' => [$credited->id, $plain->id], 'ai_parts' => [['part' => 'alto', 'voice' => $credited->ai_voice_id], ['part' => 'tenor', 'voice' => $plain->ai_voice_id]]]);

        $guides = app(HarmonyFacts::class)->guides($harmony);

        $this->assertSame([
            ['name' => null, 'part' => 'Alto', 'ai' => true, 'aiBasedOn' => 'Ada E.', 'ownVoice' => false],
            ['name' => null, 'part' => 'Tenor', 'ai' => true, 'aiBasedOn' => null, 'ownVoice' => false],
        ], $guides);

        // Retired since (a real voice took the part): still labelled, as the mix still holds it (SharedAiSnapshotTest).
        TemplateGuide::withAi()->whereKey($plain->id)->delete();
        $this->assertCount(2, app(HarmonyFacts::class)->guides($harmony));
    }
}
