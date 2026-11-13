<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Enums\CorrectionKind;
use App\Support\Flags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BetaFixtures;
use Tests\TestCase;

/**
 * Suggest a correction on the public pages (docs/corrections-plan.md): a song, part, course, lesson and tool page each
 * end with the link and its sheet, carrying the page's type and path for POST /api/corrections, the inbox's four kinds,
 * and a trap field left out of what people see; other pages have none.
 */
final class CorrectionFormTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    public function test_each_kind_of_page_ends_with_the_link_and_its_sheet(): void
    {
        config(['app.is_nexus' => true, 'app.coming_soon' => false]);
        app(Flags::class)->set('plus_courses', 'on', null, 'test');
        $this->catalogue();

        foreach (['song' => '/songs/amazing-grace', 'part' => '/songs/amazing-grace/alto', 'course' => '/courses/vibrato', 'tool' => '/tools/tuner'] as $type => $path) {
            $html = (string) $this->get($path)->assertOk()->getContent();
            $this->assertStringContainsString('data-correction-open="correction-'.$type.'"', $html, $path.' has the link');
            $this->assertMatchesRegularExpression('#<dialog id="correction-'.$type.'"[^>]*data-type="'.$type.'"[^>]*data-path="'.preg_quote($path, '#').'"#', $html, $path);
            foreach (CorrectionKind::cases() as $kind) {
                $this->assertStringContainsString('name="kind" value="'.$kind->value.'"', $html, $path.' '.$kind->value);
            }
            $this->assertStringContainsString('class="s-fix-trap" aria-hidden="true"', $html);
            $this->assertStringContainsString('/js/public/correction.js', $html);
        }

        $this->assertStringNotContainsString('data-correction-open', (string) $this->get('/pricing')->getContent(), 'not on every page');
    }
}
