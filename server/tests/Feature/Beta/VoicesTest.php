<?php

declare(strict_types=1);

namespace Tests\Feature\Beta;

use App\Actions\Beta\ManageGuides;
use App\Models\BetaSubmission;
use App\Models\TemplateGuide;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\BetaFixtures;
use Tests\TestCase;

/**
 * Every accepted recording is a published version; Hillel picks the default (his decision).
 */
final class VoicesTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    /**
     * @return array{0: TemplateGuide, 1: TemplateGuide, 2: TemplateGuide}
     */
    private function threeAltos(): array
    {
        $template = $this->catalogue();
        $versions = [];
        foreach ([3, 2, 1] as $daysAgo) {
            [, $assignment] = $this->singerWithAlto();
            $submission = BetaSubmission::factory()->create(['beta_assignment_id' => $assignment->id, 'template_version_id' => $template->current_version_id]);
            $versions[] = TemplateGuide::factory()->create(['template_version_id' => $template->current_version_id, 'part_key' => 'alto', 'beta_submission_id' => $submission->id, 'is_default' => false, 'published_at' => now()->subDays($daysAgo)]);
        }

        return [$versions[0], $versions[1], $versions[2]];
    }

    public function test_the_catalogue_lists_every_version_the_default_first(): void
    {
        [$old, $middle, $newest] = $this->threeAltos();
        app(ManageGuides::class)->makeDefault($middle, User::factory()->admin()->create());

        $alto = collect((array) $this->getJson('/api/templates')->json('data'))->firstWhere('id', 'amazing-grace')['parts'] ?? [];
        $alto = collect($alto)->firstWhere('key', 'alto');

        $this->assertSame($middle->sqid(), $alto['guide']['id']);
        $this->assertSame([$middle->sqid(), $newest->sqid(), $old->sqid()], array_column($alto['versions'], 'id'));
        $this->assertSame([true, false, false], array_column($alto['versions'], 'default'));
    }

    public function test_when_the_default_goes_hillels_latest_choice_takes_over_then_the_newest_then_the_synth(): void
    {
        Http::fake();
        $admin = User::factory()->admin()->create();
        [$old, $middle, $newest] = $this->threeAltos();
        $guides = app(ManageGuides::class);

        $guides->makeDefault($old, $admin);
        $this->travel(1)->minutes();
        $guides->makeDefault($middle, $admin);

        $guides->remove($middle, $admin);
        $this->assertTrue($old->refresh()->is_default, 'His earlier choice, over the newest.');

        $guides->remove($old, $admin);
        $this->assertTrue($newest->refresh()->is_default);

        $guides->remove($newest, $admin);
        $alto = collect(collect((array) $this->getJson('/api/templates')->json('data'))->firstWhere('id', 'amazing-grace')['parts'] ?? [])->firstWhere('key', 'alto');
        $this->assertNull($alto['guide']);
        $this->assertSame([], $alto['versions']);
    }
}
