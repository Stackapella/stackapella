<?php

declare(strict_types=1);

namespace Tests\Feature\AiVoices;

use App\Actions\AiVoices\WithdrawAiVoice;
use App\Actions\Sharing\StartHarmony;
use App\Models\AiVoice;
use App\Models\Harmony;
use App\Models\TemplateGuide;
use App\Support\AiVoices\AiGuides;
use App\Support\Sharing\HarmonyFacts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BetaFixtures;
use Tests\TestCase;

/**
 * A share's AI parts are kept on the share itself (the deep review): a real take that replaces a part, or a voice
 * withdrawn, retires the guide, while the shared mix still holds the AI singing, so its page still says so; a withdrawn
 * singer is no longer named.
 */
final class SharedAiSnapshotTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    /**
     * @return array{0: Harmony, 1: TemplateGuide, 2: TemplateGuide}
     */
    private function shared(): array
    {
        $template = $this->catalogue();
        $credited = TemplateGuide::factory()->ai()->create(['template_version_id' => $template->current_version_id, 'part_key' => 'alto']);
        $plain = TemplateGuide::factory()->ai(AiVoice::factory()->consented()->create(['based_on' => 'Bo B.', 'credited' => false]))->create(['template_version_id' => $template->current_version_id, 'part_key' => 'tenor']);
        // As harmonies.create keeps it (StartHarmony::aiParts).
        $snapshot = \Closure::bind(fn (array $ids): ?array => $this->aiParts($ids), app(StartHarmony::class), StartHarmony::class)([$credited->id, $plain->id]);
        $harmony = Harmony::factory()->create(['template_version_id' => $template->current_version_id, 'guide_ids' => [$credited->id, $plain->id], 'ai_parts' => $snapshot]);

        return [$harmony, $credited, $plain];
    }

    public function test_a_real_take_replacing_the_part_keeps_the_shares_ai_label(): void
    {
        [$harmony, , $plain] = $this->shared();

        DB::transaction(fn () => AiGuides::retireForPart($plain->template_version_id, 'tenor'));
        $this->assertNull(TemplateGuide::withAi()->find($plain->id));

        $this->assertSame([
            ['name' => null, 'part' => 'Alto', 'ai' => true, 'aiBasedOn' => 'Ada E.', 'ownVoice' => false],
            ['name' => null, 'part' => 'Tenor', 'ai' => true, 'aiBasedOn' => null, 'ownVoice' => false],
        ], app(HarmonyFacts::class)->guides($harmony->refresh()));
    }

    public function test_a_withdrawn_voice_keeps_the_label_but_no_longer_names_its_singer(): void
    {
        [$harmony, $credited] = $this->shared();

        app(WithdrawAiVoice::class)->handle(AiVoice::query()->findOrFail($credited->ai_voice_id), null);
        $this->assertNull(TemplateGuide::withAi()->find($credited->id));

        $this->assertSame(['name' => null, 'part' => 'Alto', 'ai' => true, 'aiBasedOn' => null, 'ownVoice' => false], app(HarmonyFacts::class)->guides($harmony->refresh())[0]);
    }

    public function test_shares_made_before_keep_their_ai_parts_from_the_guides_still_there(): void
    {
        [$harmony] = $this->shared();
        DB::table('harmonies')->where('id', $harmony->id)->update(['ai_parts' => null]);

        $migration = require database_path('migrations/2026_11_20_170000_add_ai_parts_to_harmonies.php');
        $migration->up();
        $migration->up();

        $this->assertSame(['alto', 'tenor'], array_column((array) $harmony->refresh()->ai_parts, 'part'));
    }
}
