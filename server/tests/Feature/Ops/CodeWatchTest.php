<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Code watch (docs/code-watch-plan.md): marker strings in other people's public code, and other
 * websites calling our API, counted per domain per day. GitHub is faked.
 */
final class CodeWatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_weekly_search_lists_repositories_outside_our_own(): void
    {
        Http::preventStrayRequests();
        $this->artisan('app:code-watch:search')->expectsOutputToContain('nothing to do')->assertSuccessful();

        config(['codewatch.markers' => ['first-marker', 'second, marker'], 'codewatch.github_token' => 'ghp_test', 'codewatch.own_owners' => ['hillelcoren']]);
        Http::fake(['api.github.com/search/code*' => fn (Request $r) => Http::response(['items' => str_contains((string) $r['q'], 'first') ? [
            ['repository' => ['full_name' => 'someone/copied-app', 'html_url' => 'https://github.com/someone/copied-app']],
            ['repository' => ['full_name' => 'someone/copied-app', 'html_url' => 'https://github.com/someone/copied-app']],
            ['repository' => ['full_name' => 'HillelCoren/stackapella', 'html_url' => 'https://github.com/hillelcoren/stackapella']],
        ] : []])]);

        $this->artisan('app:code-watch:search')->expectsOutput('Repositories seen: 1')->assertSuccessful();
        Http::assertSent(fn (Request $r): bool => $r['q'] === '"first-marker"' && $r->hasHeader('Authorization', 'Bearer ghp_test'));
        $this->assertSame(['someone/copied-app'], DB::table('code_watch_hits')->pluck('key')->all());

        // A second week: seen again, still one row.
        $this->artisan('app:code-watch:search')->assertSuccessful();
        $this->assertSame(1, DB::table('code_watch_hits')->count());
    }

    public function test_other_sites_calling_the_api_are_counted_by_domain_and_listed_past_the_threshold(): void
    {
        config(['codewatch.domain_threshold' => 3]);

        foreach (range(1, 3) as $i) {
            $this->withHeader('Origin', 'https://www.copycat.example')->getJson('/api/auth/config')->assertOk();
        }

        // Our own hosts and local ones aren't counted; nothing but the domain and a count is kept.
        $this->withHeader('Origin', 'https://stackapella.test')->getJson('/api/auth/config')->assertOk();
        $this->withHeader('Referer', 'http://localhost:8000/x')->getJson('/api/auth/config')->assertOk();
        $this->assertSame([['domain' => 'copycat.example', 'count' => 3]], DB::table('code_watch_domain_days')->get(['domain', 'count'])->map(fn ($r): array => (array) $r)->all());

        $this->actingAsAdmin();
        $list = $this->getJson('/api/admin/code-watch');
        $this->assertMatchesContract($list, 'admin.codeWatch.list', 200);
        $list->assertJsonPath('data.hits.0.kind', 'domain')->assertJsonPath('data.hits.0.key', 'copycat.example')->assertJsonPath('data.hits.0.calls', 3);

        $id = (string) $list->json('data.hits.0.id');
        $updated = $this->patchJson('/api/admin/code-watch/'.$id, ['dismissed' => true, 'note' => 'Credits us in the footer.']);
        $this->assertMatchesContract($updated, 'admin.codeWatch.update', 200);
        $updated->assertJsonPath('data.note', 'Credits us in the footer.');
        $this->assertNotNull($updated->json('data.dismissedAt'));
        $this->assertMatchesContract($this->patchJson('/api/admin/code-watch/zzzzzz', ['dismissed' => true]), 'admin.codeWatch.update', 404);

        $this->actingAs(User::factory()->create());
        $this->getJson('/api/admin/code-watch')->assertForbidden();
    }
}
