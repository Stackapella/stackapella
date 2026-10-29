<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Site\HelpGuide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/** The press kit's screenshots: every one it names is shot (light and dark), and each is offered in both. */
final class PressKitTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_screenshot_is_shot_and_offered_light_and_dark(): void
    {
        $page = $this->get('/press')->assertOk()->assertSee(__('site.press_words.screenshots'));

        foreach ((array) Lang::get('site.press_words.shots') as $id => $caption) {
            $this->assertNotNull(HelpGuide::shot((string) $id), "{$id} isn't shot: run the help_shots flow");
            $page->assertSee('href="/icons/help/'.$id.'.png"', false)->assertSee('href="/icons/help/'.$id.'-dark.png"', false)->assertSee((string) $caption);
        }
    }
}
