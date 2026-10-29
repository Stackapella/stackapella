<?php

declare(strict_types=1);

namespace Tests\Feature\Templates;

use App\Actions\Templates\SyncOfficialTemplates;
use App\Http\Resources\Beta\BetaPartResource;
use App\Models\TemplateVersion;
use App\Support\Templates\TemplateRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * A song's (and a part's) vibrato style (docs/vibrato-plan.md): metadata, so a change never needs
 * a new version and recorded guides keep their version.
 */
final class StyleTest extends TestCase
{
    use RefreshDatabase;

    public function test_style_changes_in_place_and_a_wrong_one_is_refused(): void
    {
        $folder = storage_path('framework/testing/styles-'.uniqid());
        File::ensureDirectoryExists($folder);
        config(['templates.folders' => [$folder]]);
        $song = (array) json_decode((string) File::get(base_path('database/templates/sweet-adeline.json')), true);
        $this->assertSame('straight', $song['style']);

        File::put($folder.'/sweet-adeline.json', (string) json_encode([...$song, 'style' => 'free']));
        app(SyncOfficialTemplates::class)->handle();
        File::put($folder.'/sweet-adeline.json', (string) json_encode($song));
        app(SyncOfficialTemplates::class)->handle();

        $this->assertSame(1, TemplateVersion::query()->count());
        $this->assertSame('straight', TemplateVersion::query()->sole()->data['style']);

        $bad = [...$song, 'style' => 'wobbly'];
        $this->assertContains('style must be one of straight, free, vibrato', app(TemplateRules::class)->problems($bad));
        File::deleteDirectory($folder);
    }

    public function test_a_part_says_its_own_style_over_the_songs(): void
    {
        $data = ['style' => 'straight', 'parts' => [['key' => 'lead', 'style' => 'vibrato'], ['key' => 'bass']]];

        $this->assertSame('vibrato', BetaPartResource::style($data, 'lead'));
        $this->assertSame('straight', BetaPartResource::style($data, 'bass'));
        $this->assertSame('free', BetaPartResource::style([], 'alto'));
    }
}
