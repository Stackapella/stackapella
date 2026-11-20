<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Actions\Templates\SyncOfficialTemplates;
use App\Models\AiVoice;
use App\Models\Template;
use App\Models\TemplateGuide;
use App\Support\Flags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * An AI part on the song and part pages (docs/ai-voices-build-plan.md, marketing item 1; design rules, "AI: plain about
 * it, never loud"): named once on its part in the app's words, with "Real voice wanted: be the voice" where Be the voice
 * is open and one link to About AI voices; nothing at all with ai_voices off, or on a song with no AI part.
 */
final class AiVoiceLabelsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(SyncOfficialTemplates::class)->handle();
    }

    private function aiAlto(?AiVoice $voice = null): void
    {
        $template = Template::query()->where('slug', 'amazing-grace')->firstOrFail();
        $guide = TemplateGuide::factory()->ai($voice)->create(['template_version_id' => $template->current_version_id, 'part_key' => 'alto']);
        Storage::disk('media')->put($guide->media_key, 'aac');
    }

    public function test_switched_off_the_pages_are_as_they_were(): void
    {
        $this->aiAlto();

        $song = (string) $this->get('/songs/amazing-grace')->assertOk()->getContent();
        $this->assertSame(4, substr_count($song, '<span>'.e(__('pages.song.voice_generated')).'</span>'));
        $part = (string) $this->get('/songs/amazing-grace/alto')->assertOk()->getContent();
        foreach ([$song, $part] as $html) {
            $this->assertStringNotContainsString('data-ai-voice', $html);
            $this->assertStringNotContainsString('/about/ai-voices', $html);
            $this->assertStringNotContainsString(e(__('pages.song.ai.credit')), $html);
        }
    }

    public function test_switched_on_the_ai_part_says_so_once_on_its_part_with_one_link_to_about_ai_voices(): void
    {
        $this->aiAlto();
        app(Flags::class)->set('ai_voices', 'on', null, 'AI voices');
        $named = e(__('pages.song.ai.based_on', ['name' => 'Ada E.']));

        $song = (string) $this->get('/songs/amazing-grace')->assertOk()->getContent();
        $this->assertSame(1, substr_count($song, '<span data-ai-voice>'.$named.'</span>'));
        $this->assertSame(3, substr_count($song, '<span>'.e(__('pages.song.voice_generated')).'</span>'), 'The other parts are as they were.');
        $this->assertSame(1, substr_count($song, 'href="/about/ai-voices"'));
        // The song page's player may sing it, since the page names it; no other player does (mixer.js).
        $this->assertStringContainsString('"aiVoices":true', $song);

        $part = (string) $this->get('/songs/amazing-grace/alto')->assertOk()->getContent();
        $this->assertStringContainsString('<span data-ai-voice>'.$named.'</span>', $part);
        $this->assertSame(1, substr_count($part, 'href="/about/ai-voices"'));
        $this->assertStringNotContainsString(e(__('site.song_part.synth')), $part);

        // Another part, and another song, show nothing new.
        $tenor = (string) $this->get('/songs/amazing-grace/tenor')->assertOk()->getContent();
        $this->assertStringNotContainsString('data-ai-voice', $tenor);
        $this->assertStringNotContainsString('/about/ai-voices', $tenor);
        $this->assertStringNotContainsString('/about/ai-voices', (string) $this->get('/songs/silent-night')->assertOk()->getContent());
    }

    public function test_a_voice_whose_singer_chose_no_credit_has_the_plain_label(): void
    {
        $this->aiAlto(AiVoice::factory()->consented()->create(['based_on' => 'Ada E.']));
        app(Flags::class)->set('ai_voices', 'on', null, 'AI voices');

        $song = (string) $this->get('/songs/amazing-grace')->assertOk()->getContent();
        $this->assertStringContainsString('<span data-ai-voice>'.e(__('pages.song.ai.credit')).'</span>', $song);
        $this->assertStringNotContainsString('Ada E.', $song);
    }

    public function test_with_be_the_voice_open_the_ai_part_asks_for_a_real_voice(): void
    {
        $this->aiAlto();
        app(Flags::class)->set('ai_voices', 'on', null, 'AI voices');
        config(['flags.lend_voice' => ['kind' => 'feature', 'values' => ['off', 'on']]]);
        app(Flags::class)->set('lend_voice', 'on', null, 'Lend your voice');

        $song = (string) $this->get('/songs/amazing-grace')->assertOk()->getContent();
        $this->assertStringContainsString('data-be-voice="alto">'.e(__('pages.song.ai.wanted')).'</a>', $song);
        $this->assertStringContainsString('data-be-voice="tenor">'.e(__('pages.song.be_voice', ['part' => 'tenor'])).'</a>', $song);
        // The note under the parts is true of a part sung by AI too ("the app's own voice" isn't).
        $this->assertStringContainsString(e(__('pages.song.ai.note')), $song);
        $this->assertStringNotContainsString(e(__('pages.song.be_voice_note')), $song);

        $part = (string) $this->get('/songs/amazing-grace/alto')->assertOk()->getContent();
        $this->assertStringContainsString('data-be-voice="alto">'.e(__('pages.song.ai.wanted')).'</a>', $part);
        $this->assertStringContainsString(e(__('pages.song.ai.note')), $part);
    }

    public function test_the_pages_that_explain_guide_voices_mention_ai_voices_in_one_sentence_only_while_on(): void
    {
        $sentence = 'Until a real singer records a part, it may have an AI voice for now, always marked as AI.';
        foreach (['/help/guides', '/features/real-voices'] as $path) {
            $this->assertStringNotContainsString($sentence, (string) $this->get($path)->assertOk()->getContent(), $path);
        }

        app(Flags::class)->set('ai_voices', 'on', null, 'AI voices');

        $help = (string) $this->get('/help/guides')->assertOk()->getContent();
        $this->assertSame(1, substr_count($help, e($sentence)));
        $this->assertSame(1, substr_count($help, 'href="/about/ai-voices"'));
        $this->assertSame(1, substr_count((string) $this->get('/features/real-voices')->assertOk()->getContent(), e($sentence)));
    }
}
