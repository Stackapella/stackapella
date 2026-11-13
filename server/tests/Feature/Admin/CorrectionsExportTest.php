<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\CorrectionState;
use App\Models\Correction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The corrections export for the courses lead's daily triage (Internal\CorrectionsExportController, app:pull-corrections):
 * off until its own token is set, the token compared in constant time, read-only, only the open song and course
 * corrections, and never anything about the reader.
 */
final class CorrectionsExportTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'a-test-token-of-forty-characters-0123456';

    public function test_off_without_a_token_long_enough(): void
    {
        config(['services.corrections_export.token' => null]);
        $this->withToken(self::TOKEN)->getJson('/api/internal/corrections')->assertNotFound();
        config(['services.corrections_export.token' => 'short']);
        $this->withToken('short')->getJson('/api/internal/corrections')->assertNotFound();
    }

    public function test_a_wrong_or_missing_token_is_refused(): void
    {
        config(['services.corrections_export.token' => self::TOKEN]);
        $this->getJson('/api/internal/corrections')->assertUnauthorized();
        $this->withToken(self::TOKEN.'x')->getJson('/api/internal/corrections')->assertUnauthorized();
        $this->withToken(strrev(self::TOKEN))->getJson('/api/internal/corrections')->assertUnauthorized();
        // Compared in constant time (hash_equals), never with ==.
        $this->assertStringContainsString('hash_equals($expected, $given)', (string) file_get_contents(app_path('Http/Controllers/Internal/CorrectionsExportController.php')));
    }

    public function test_only_open_song_and_course_corrections_with_no_reader_fields_and_nothing_changed(): void
    {
        config(['services.corrections_export.token' => self::TOKEN]);
        $song = Correction::factory()->create(['page_type' => 'song', 'page_path' => '/songs/amazing-grace', 'created_at' => now()->subHour()]);
        $lesson = Correction::factory()->create(['page_type' => 'lesson', 'page_path' => '/courses/barbershop']);
        Correction::factory()->create(['page_type' => 'tool', 'page_path' => '/tools/tuner']);
        Correction::factory()->create(['page_type' => 'part'])->forceFill(['state' => CorrectionState::Done])->save();
        Correction::factory()->create(['page_type' => 'course'])->forceFill(['state' => CorrectionState::Rejected, 'note' => 'No.'])->save();
        $before = DB::table('corrections')->orderBy('id')->get()->toJson();

        $answer = $this->withToken(self::TOKEN)->getJson('/api/internal/corrections')->assertOk()->assertHeader('Cache-Control', 'no-store, private');

        $this->assertSame([$song->sqid(), $lesson->sqid()], array_column((array) $answer->json('corrections'), 'id'), 'open ones on song and course pages, oldest first');
        $this->assertSame(['id', 'createdAt', 'state', 'kind', 'pageType', 'pagePath', 'pageTitle', 'lang', 'text', 'source'], array_keys((array) $answer->json('corrections.0')), 'exactly these fields: nothing about the reader, nor the team\'s decision');
        $this->assertSame(2, $answer->json('meta.count'));
        $this->assertSame($before, DB::table('corrections')->orderBy('id')->get()->toJson(), 'read-only');
    }

    public function test_the_pull_writes_the_days_file_and_never_sends_the_token_elsewhere(): void
    {
        config(['services.corrections_export.token' => self::TOKEN, 'services.corrections_export.url' => 'https://app.stackapella.test']);
        $dir = storage_path('framework/testing/corrections-'.getmypid());
        File::deleteDirectory($dir);
        Http::fake(['app.stackapella.test/api/internal/corrections' => Http::response(['meta' => ['count' => 1], 'corrections' => [['id' => 'abc']]])]);

        $this->artisan('app:pull-corrections', ['--to' => $dir])->expectsOutputToContain('1 open corrections saved to')->assertSuccessful();
        $this->assertSame([['id' => 'abc']], json_decode((string) File::get($dir.'/'.now()->toDateString().'.json'), true)['corrections']);
        Http::assertSent(static fn ($request): bool => $request->hasHeader('Authorization', 'Bearer '.self::TOKEN));

        $this->artisan('app:pull-corrections', ['--to' => $dir, '--url' => 'https://elsewhere.example.com'])->expectsOutputToContain('Refusing')->assertFailed();
        config(['services.corrections_export.token' => null]);
        $this->artisan('app:pull-corrections', ['--to' => $dir])->expectsOutputToContain('CORRECTIONS_EXPORT_TOKEN is missing')->assertFailed();
        File::deleteDirectory($dir);
    }
}
