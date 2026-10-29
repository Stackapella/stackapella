<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Templates\SyncOfficialTemplates;
use App\Enums\TemplateAccess;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Support\Templates\TemplateRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use LogicException;
use Tests\TestCase;

final class SyncTemplatesTest extends TestCase
{
    use RefreshDatabase;

    private string $folder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->folder = storage_path('framework/testing/templates-'.uniqid());
        File::ensureDirectoryExists($this->folder);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->folder);

        parent::tearDown();
    }

    public function test_every_official_template_is_loaded_with_its_access(): void
    {
        $this->artisan('app:templates:sync')->assertSuccessful();

        $files = array_filter([...glob(dirname(base_path()).'/web/templates/*.json') ?: [], ...glob(database_path('templates/*.json')) ?: []], static fn (string $f): bool => basename($f) !== 'index.json');
        // A song may be in both folders while it moves from the app's files to the server's.
        $count = count(array_unique(array_map('basename', $files)));
        $this->assertSame($count, Template::query()->count());
        $this->assertSame(TemplateAccess::Free, Template::query()->where('slug', 'amazing-grace')->firstOrFail()->access);
        $this->assertSame(TemplateAccess::Plus, Template::query()->where('slug', 'silent-night')->firstOrFail()->access);
        $this->assertNotNull(Template::query()->where('slug', 'amazing-grace')->firstOrFail()->currentVersion);

        // Again: nothing changes.
        $this->artisan('app:templates:sync')->expectsOutput(count($files).' unchanged.')->assertSuccessful();
        $this->assertSame($count, TemplateVersion::query()->count());
    }

    public function test_a_published_version_is_never_overwritten(): void
    {
        config(['templates.folders' => [$this->folder]]);
        $template = [...(array) json_decode((string) file_get_contents(dirname(base_path()).'/web/templates/amazing-grace.json'), true), 'reviewed' => false];
        File::put($this->folder.'/amazing-grace.json', (string) json_encode($template));
        $this->artisan('app:templates:sync')->assertSuccessful();

        $template['parts'][0]['guide']['notes'][0]['pitch'] = 63;
        File::put($this->folder.'/amazing-grace.json', (string) json_encode($template));
        $this->artisan('app:templates:sync')->assertFailed();
        $this->assertSame(62, TemplateVersion::query()->firstOrFail()->data['parts'][0]['guide']['notes'][0]['pitch']);

        $template['version']++;
        File::put($this->folder.'/amazing-grace.json', (string) json_encode($template));
        $this->artisan('app:templates:sync')->assertSuccessful();
        $this->assertSame($template['version'], Template::query()->firstOrFail()->currentVersion?->version);

        $this->expectException(LogicException::class);
        TemplateVersion::query()->firstOrFail()->forceFill(['reviewed' => true])->save();
    }

    public function test_a_catalogue_published_by_the_first_deploy_syncs_and_still_guards_its_music(): void
    {
        $this->artisan('app:templates:sync')->assertSuccessful();

        // The first deploy (ac0789d) stored a hash of the whole file.
        foreach (TemplateVersion::query()->get() as $version) {
            TemplateVersion::query()->whereKey($version->id)->update(['sha256' => hash('sha256', (string) json_encode($version->data))]);
        }

        $this->artisan('app:templates:sync')->assertSuccessful();
        foreach (TemplateVersion::query()->get() as $version) {
            $this->assertSame(SyncOfficialTemplates::musicHash((array) $version->data), $version->sha256);
        }

        config(['templates.folders' => [$this->folder]]);
        $template = (array) json_decode((string) file_get_contents(dirname(base_path()).'/web/templates/amazing-grace.json'), true);
        TemplateVersion::query()->whereHas('template', static fn ($q) => $q->where('slug', 'amazing-grace'))->update(['sha256' => hash('sha256', (string) json_encode($template))]);
        $template['parts'][0]['guide']['notes'][0]['pitch']++;
        File::put($this->folder.'/amazing-grace.json', (string) json_encode($template));
        $this->artisan('app:templates:sync')->assertFailed();
    }

    public function test_the_words_around_the_music_and_the_reviewed_mark_can_change_in_place(): void
    {
        config(['templates.folders' => [$this->folder]]);
        $template = (array) json_decode((string) file_get_contents(dirname(base_path()).'/web/templates/amazing-grace.json'), true);
        File::put($this->folder.'/amazing-grace.json', (string) json_encode($template));
        $this->artisan('app:templates:sync')->assertSuccessful();

        $template['description'] = 'A fixed typo.';
        $template['reviewed'] = true;
        File::put($this->folder.'/amazing-grace.json', (string) json_encode($template));
        $this->artisan('app:templates:sync')->assertSuccessful();

        $version = TemplateVersion::query()->firstOrFail();
        $this->assertTrue($version->reviewed);
        $this->assertSame('A fixed typo.', $version->data['description']);
    }

    public function test_the_current_version_is_the_newest_reviewed_one(): void
    {
        config(['templates.folders' => [$this->folder]]);
        $template = (array) json_decode((string) file_get_contents(dirname(base_path()).'/web/templates/amazing-grace.json'), true);
        $template['reviewed'] = true;
        File::put($this->folder.'/a.json', (string) json_encode($template));
        $draft = [...$template, 'version' => $template['version'] + 1, 'reviewed' => false, 'tempo' => 90];
        File::put($this->folder.'/b.json', (string) json_encode($draft));

        $this->artisan('app:templates:sync')->assertSuccessful();

        $this->assertSame($template['version'], Template::query()->firstOrFail()->currentVersion?->version);
    }

    public function test_a_broken_template_is_refused_with_its_problems(): void
    {
        config(['templates.folders' => [$this->folder]]);
        File::put($this->folder.'/broken.json', (string) json_encode(['id' => 'broken', 'version' => 1, 'tempo' => 900]));

        $this->artisan('app:templates:sync')->assertFailed();
        $this->assertSame(0, Template::query()->count());
    }

    public function test_a_song_whose_file_is_gone_leaves_the_library_but_its_projects_keep_it(): void
    {
        $folder = dirname(base_path()).'/web/templates';
        foreach (['amazing-grace', 'happy-birthday', 'are-you-sleeping', 'michael-row'] as $id) {
            File::copy($folder.'/'.$id.'.json', $this->folder.'/'.$id.'.json');
        }
        config(['templates.folders' => [$this->folder]]);
        app(SyncOfficialTemplates::class)->handle();
        $this->get('/songs/happy-birthday')->assertOk();

        // Its file leaves the content (Hillel: "Remove them now"): withdrawn, not deleted.
        File::delete($this->folder.'/happy-birthday.json');
        $report = app(SyncOfficialTemplates::class)->handle();
        $this->assertSame(['happy-birthday'], $report['withdrawn']);
        $this->assertSame([], $report['problems']);
        $this->assertNotNull(Template::query()->where('slug', 'happy-birthday')->value('withdrawn_at'));

        $this->get('/songs/happy-birthday')->assertNotFound();
        $this->assertStringNotContainsString('/songs/happy-birthday', (string) $this->get('/sitemap.xml')->getContent());
        $this->assertNotContains('happy-birthday', array_column((array) $this->getJson('/api/templates')->json('data'), 'id'));
        // A project made from it still loads it.
        $this->getJson('/api/templates/happy-birthday')->assertOk();

        // Back in the content: back in the library.
        File::copy($folder.'/happy-birthday.json', $this->folder.'/happy-birthday.json');
        $this->assertSame([], app(SyncOfficialTemplates::class)->handle()['withdrawn']);
        $this->get('/songs/happy-birthday')->assertOk();
    }

    public function test_a_content_fetch_that_went_wrong_withdraws_nothing(): void
    {
        $folder = dirname(base_path()).'/web/templates';
        foreach (['amazing-grace', 'happy-birthday', 'are-you-sleeping', 'michael-row'] as $id) {
            File::copy($folder.'/'.$id.'.json', $this->folder.'/'.$id.'.json');
        }
        config(['templates.folders' => [$this->folder]]);
        app(SyncOfficialTemplates::class)->handle();

        File::cleanDirectory($this->folder);
        $report = app(SyncOfficialTemplates::class)->handle();
        $this->assertSame([], $report['withdrawn']);
        $this->assertStringContainsString('not withdrawn', implode(' ', $report['problems']));
        $this->assertSame(0, Template::query()->whereNotNull('withdrawn_at')->count());
    }

    public function test_an_official_template_may_be_barbershop_but_never_a_mix(): void
    {
        $rules = app(TemplateRules::class);
        $part = static fn (string $key, bool $melody = false): array => ['key' => $key, 'kind' => 'voice', ...($melody ? ['melody' => true] : [])];

        $this->assertSame([], $rules->officialProblems(['parts' => [$part('tenor'), $part('lead', true), $part('baritone'), $part('bass')]]));
        $this->assertSame([], $rules->officialProblems(['parts' => [$part('soprano', true), $part('alto'), $part('tenor'), $part('bass')]]));
        $mixed = $rules->officialProblems(['parts' => [$part('soprano'), $part('lead', true), $part('baritone'), $part('bass')]]);
        $this->assertCount(1, $mixed);
        $this->assertStringContainsString('never mixed with soprano or alto', $mixed[0]);
    }

    public function test_every_part_key_enum_in_the_contract_has_both_sets(): void
    {
        foreach (['catalogue.part.json' => 'key', 'beta.part.json' => 'partKey', 'admin.beta.assignment.json' => 'partKey', 'admin.beta.submission.json' => 'partKey'] as $schema => $field) {
            $enum = (array) json_decode((string) file_get_contents(base_path('../contract/schemas/'.$schema)), true)['properties'][$field]['enum'];
            foreach ([...TemplateRules::OFFICIAL_KEYS, ...TemplateRules::BARBERSHOP_KEYS] as $key) {
                $this->assertContains($key, $enum, "{$schema}'s {$field} can't be {$key}");
            }
        }
    }

    public function test_an_official_template_stays_satb_and_all_voices(): void
    {
        config(['templates.folders' => [$this->folder]]);
        $data = (array) json_decode((string) file_get_contents(dirname(base_path()).'/web/templates/amazing-grace.json'), true);
        $data['parts'][3] = [...$data['parts'][3], 'key' => 'piano', 'kind' => 'instrument'];
        $data['voicings'] = ['1' => ['soprano']];
        $data['order'] = array_values(array_filter((array) ($data['order'] ?? []), static fn (string $key): bool => $key !== 'bass'));
        File::put($this->folder.'/amazing-grace.json', (string) json_encode($data));

        $this->artisan('app:templates:sync')->expectsOutputToContain('official templates are soprano, alto, tenor and bass')->assertFailed();
        $this->assertSame(0, Template::query()->count());
    }
}
