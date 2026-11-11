<?php

declare(strict_types=1);

namespace Tests\Feature\Templates;

use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * templates.check (docs/score-import-plan.md, section 3): a draft's problems (what would refuse its save) and its
 * advice (never applied), before it's saved; nothing is kept.
 */
final class TemplateCheckTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private static function song(): array
    {
        return (array) json_decode((string) file_get_contents(base_path('../web/templates/amazing-grace.json')), true);
    }

    public function test_a_good_song_has_no_problems_and_a_broken_one_says_what_stops_it(): void
    {
        $this->actingAs(User::factory()->create());

        $good = $this->postJson('/api/templates/check', ['template' => self::song()]);
        $this->assertMatchesContract($good, 'templates.check', 200);
        $good->assertJsonPath('data.problems', []);
        foreach ((array) $good->json('data.advice') as $advice) {
            $this->assertNotSame('', $advice['kind']);
        }

        // A part that runs past the song's end: a problem, as its save would be refused.
        $song = self::song();
        $notes = $song['parts'][0]['guide']['notes'];
        $last = $notes[array_key_last($notes)];
        $song['parts'][0]['guide']['notes'][] = ['beat' => $last['beat'] + $last['length'] + 8, 'length' => 1, 'pitch' => $last['pitch'], 'lyric' => 'la'];
        $this->assertNotSame([], $this->postJson('/api/templates/check', ['template' => $song])->assertOk()->json('data.problems'));

        // Not a template at all: the schema says why.
        $this->assertNotSame([], $this->postJson('/api/templates/check', ['template' => ['id' => 'x', 'tempo' => 900]])->assertOk()->json('data.problems'));
        $this->assertMatchesContract($this->postJson('/api/templates/check', []), 'templates.check', 422);
        $this->assertSame(0, Template::query()->count());
    }

    public function test_it_is_limited_and_needs_an_account(): void
    {
        $this->postJson('/api/templates/check', ['template' => self::song()])->assertUnauthorized();

        $this->actingAs(User::factory()->create());
        config(['limits.template-check.account.max' => 1]);
        $this->postJson('/api/templates/check', ['template' => self::song()])->assertOk();
        $this->assertMatchesContract($this->postJson('/api/templates/check', ['template' => self::song()]), 'templates.check', 429);
    }
}
