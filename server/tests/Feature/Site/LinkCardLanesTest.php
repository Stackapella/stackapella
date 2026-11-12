<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Models\Template;
use App\Models\TemplateVersion;
use App\Support\Site\LinkCardLanes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LinkCardLanesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_lanes_open_once_every_part_has_come_in_and_light_the_named_part(): void
    {
        $template = Template::factory()->create(['slug' => 'drunken-sailor', 'title' => 'Drunken Sailor (shanty)']);
        $version = TemplateVersion::factory()->create(['template_id' => $template->id, 'data' => [
            'tempo' => 120,
            'parts' => [
                ['key' => 'soprano', 'name' => 'Soprano', 'guide' => ['notes' => [['beat' => 0, 'length' => 2, 'pitch' => 72], ['beat' => 4, 'length' => 4, 'pitch' => 76]]]],
                ['key' => 'alto', 'name' => 'Alto', 'guide' => ['notes' => [['beat' => 4, 'length' => 4, 'pitch' => 67]]]],
                ['key' => 'tenor', 'name' => 'Tenor', 'guide' => ['notes' => [['beat' => 4, 'length' => 4, 'pitch' => 64]]]],
                ['key' => 'bass', 'name' => 'Bass', 'guide' => ['notes' => [['beat' => 4, 'length' => 2, 'pitch' => 48], ['beat' => 6, 'length' => 2, 'pitch' => 55], ['beat' => 30, 'length' => 2, 'pitch' => 43]]]],
            ],
        ]]);
        $template->forceFill(['current_version_id' => $version->id])->save();

        $lanes = LinkCardLanes::for('sea-shanties');

        $this->assertNotNull($lanes);
        $this->assertSame('Drunken Sailor', $lanes['song'], 'A note in brackets after the title is left off, to fit the caption.');
        $this->assertSame(['Bass', 3], [$lanes['part'], $lanes['lit']]);
        $this->assertSame('0:16', $lanes['length'], 'The song runs 32 beats at 120 a minute.');
        $this->assertSame([['x' => 0.0, 'w' => 0.25, 'y' => 0.5]], $lanes['lanes'][0], 'The window starts at beat 4, where the last part comes in, so the soprano\'s pickup is left out; a lone note sits mid-lane.');
        $this->assertSame([0.0, 1.0], array_column($lanes['lanes'][3], 'y'), 'Each lane spans its own lowest to highest note in the window.');
        $this->assertCount(2, $lanes['lanes'][3], 'A note past the 16 beats is not drawn.');
        $this->assertSame(['S', 'A', 'T', 'B'], $lanes['keys'], 'Each lane is lettered from its part\'s name.');
    }

    public function test_a_collection_whose_song_is_missing_gets_no_lanes(): void
    {
        $this->assertNull(LinkCardLanes::for('carols'));
        $this->assertNull(LinkCardLanes::for('no-such-collection'));
    }
}
