<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * keep1 starts with the deploy that runs its migration (Hillel: "Start it"), on production only,
 * once: a draft is started with its guardrails and audience, and a second run changes nothing.
 */
final class StartKeep1Test extends TestCase
{
    use RefreshDatabase;

    private function migrate(string $env): void
    {
        $was = $this->app['env'];
        $this->app['env'] = $env;

        try {
            /** @var Migration $migration */
            $migration = require database_path('migrations/2026_11_05_580000_start_keep1_experiment.php');
            $migration->up();
        } finally {
            $this->app['env'] = $was;
        }
    }

    public function test_keep1_starts_on_production_only_and_only_once(): void
    {
        $this->travelTo(Carbon::parse('2026-11-02 09:00:00', 'UTC'));
        $this->migrate('testing');
        $this->assertNull(DB::table('experiments')->where('key', 'keep1')->value('starts_on'));

        $this->migrate('production');
        $keep1 = DB::table('experiments')->where('key', 'keep1')->first();
        $this->assertNotNull($keep1);
        $this->assertSame('2026-11-02', substr((string) $keep1->starts_on, 0, 10));
        $this->assertSame(['f_day14', 'f_plus'], json_decode((string) $keep1->guardrails, true));
        $this->assertSame(['signedIn' => false, 'firstWeek' => true], json_decode((string) $keep1->audience, true));

        // The app learns it: running, enrolling, guests only.
        $this->getJson('/api/auth/config')->assertJsonFragment(['id' => 'keep1', 'enrol' => true, 'audience' => ['signedIn' => false, 'firstWeek' => true]]);

        // A later deploy leaves it as it is.
        $this->travel(3)->days();
        $this->migrate('production');
        $this->assertSame('2026-11-02', substr((string) DB::table('experiments')->where('key', 'keep1')->value('starts_on'), 0, 10));
    }
}
