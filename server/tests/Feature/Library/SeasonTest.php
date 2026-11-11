<?php

declare(strict_types=1);

namespace Tests\Feature\Library;

use App\Actions\Templates\SyncOfficialTemplates;
use App\Models\Template;
use App\Support\Pages\Carols;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The catalogue's `season` (retention's "New and in season"; config templates.seasons): a carol is in season from
 * 1 November to 26 December, the date it ends given; any other song, and a carol out of season, null.
 */
final class SeasonTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function catalogue(string $when): array
    {
        $this->travelTo(Carbon::parse($when.' 12:00:00', 'UTC'));

        return collect((array) $this->getJson('/api/templates')->assertOk()->json('data'))->keyBy('id')->all();
    }

    public function test_a_carol_is_in_season_until_26_december_and_nothing_else_ever_is(): void
    {
        app(SyncOfficialTemplates::class)->handle();
        $carol = Template::query()->with('currentVersion')->get()->first(static fn (Template $t): bool => Carols::isCarol($t))?->slug;
        $this->assertIsString($carol);

        $december = $this->catalogue('2026-12-10');
        $this->assertSame(['key' => 'carols', 'until' => '2026-12-26'], $december[$carol]['season']);
        $this->assertNull($december['amazing-grace']['season']);

        $this->assertSame('2026-12-26', $this->catalogue('2026-11-01')[$carol]['season']['until'] ?? null);
        $this->assertNull($this->catalogue('2026-12-27')[$carol]['season']);
        $this->assertNull($this->catalogue('2026-10-31')[$carol]['season']);
    }
}
