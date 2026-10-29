<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * H22 in one command: the load test's singers made with their app tokens, then removed.
 */
final class LoadTestAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_makes_singers_whose_tokens_work_and_removes_them_after(): void
    {
        config(['app.url' => 'https://beta.stackapella.com']);
        $someone = User::factory()->create(['handle' => 'loadtester']);

        $this->assertSame(0, Artisan::call('app:load:accounts', ['--make' => 2]));
        $output = Artisan::output();
        $this->assertMatchesRegularExpression('#k6 run -e BASE=https://beta\.stackapella\.com -e TOKENS=sat_\S+,sat_\S+ server/tool/load/beta\.js#', $output);
        preg_match('#TOKENS=(sat_\S+),#', $output, $match);

        $this->assertSame(2, User::query()->where('handle', 'like', 'loadtest\\_%')->whereNull('email')->count());
        $this->withHeader('Authorization', 'Bearer '.$match[1])->getJson('/api/account')->assertOk()->assertJsonPath('data.handle', 'loadtest_1');

        $this->flushHeaders();
        $this->artisan('app:load:accounts --remove')->expectsOutput('Removed 2 load-test accounts.')->assertSuccessful();
        $this->assertSame(0, User::query()->where('handle', 'like', 'loadtest\\_%')->count());
        $this->assertNotNull($someone->fresh());
    }

    public function test_it_refuses_the_launch_domain(): void
    {
        config(['app.url' => 'https://stackapella.com']);
        $this->artisan('app:load:accounts')->assertFailed();
        $this->assertSame(0, User::query()->count());
    }
}
