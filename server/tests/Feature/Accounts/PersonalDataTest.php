<?php

declare(strict_types=1);

namespace Tests\Feature\Accounts;

use App\Actions\Users\DeleteAccount;
use App\Jobs\BuildExport;
use App\Models\AppDevice;
use App\Models\Choir;
use App\Models\ChoirVoice;
use App\Models\DataExport;
use App\Models\Idea;
use App\Models\IdeaVoice;
use App\Models\Part;
use App\Models\Project;
use App\Models\Report;
use App\Models\SignInLink;
use App\Models\SongRequest;
use App\Models\SongRequestVote;
use App\Models\StoreSubscription;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Models\UserLearning;
use App\Support\Accounts\PersonalData;
use App\Support\Legal\Terms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/**
 * Every column that points at a person is in their export or says why not (PersonalData), and
 * goes or is anonymised when the account is deleted. A new table pointing at people fails here
 * until it's thought about.
 */
final class PersonalDataTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every column the schema points at users with, and its delete rule.
     *
     * @return array<string, string>
     */
    private static function userColumns(): array
    {
        $database = DB::connection()->getDatabaseName();
        $found = [];

        foreach (DB::select("SELECT k.TABLE_NAME AS t, k.COLUMN_NAME AS c, r.DELETE_RULE AS d FROM information_schema.KEY_COLUMN_USAGE k
            JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA = k.TABLE_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
            WHERE k.TABLE_SCHEMA = ? AND k.REFERENCED_TABLE_NAME = 'users'", [$database]) as $row) {
            $found[$row->t.'.'.$row->c] = $row->d;
        }

        // Columns named for a person but with no foreign key: they'd outlive the account unseen.
        foreach (DB::select("SELECT c.TABLE_NAME AS t, c.COLUMN_NAME AS c FROM information_schema.COLUMNS c WHERE c.TABLE_SCHEMA = ? AND c.DATA_TYPE IN ('bigint', 'int')
            AND c.COLUMN_NAME REGEXP '(^|_)(user|owner|actor|author|singer|coach|teacher|reporter|follower|followee|blocker|blocked|creator|director|admin|buyer|sender)_id$|^(recorded|created|approved|decided|invited|reviewed|resolved|updated|used)_by$'
            AND NOT EXISTS (SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k WHERE k.TABLE_SCHEMA = c.TABLE_SCHEMA AND k.TABLE_NAME = c.TABLE_NAME AND k.COLUMN_NAME = c.COLUMN_NAME AND k.REFERENCED_TABLE_NAME IS NOT NULL)", [$database]) as $row) {
            $found[$row->t.'.'.$row->c] = 'NONE';
        }

        ksort($found);

        return $found;
    }

    public function test_every_column_pointing_at_a_person_is_exported_or_explained_and_goes_with_the_account(): void
    {
        $columns = self::userColumns();

        $this->assertSame([], array_values(array_diff(array_keys($columns), array_keys(PersonalData::COLUMNS))), 'New columns point at people: add each to PersonalData::COLUMNS, with its export file or why not, and make sure deleting the account removes it.');
        $this->assertSame([], array_values(array_diff(array_keys(PersonalData::COLUMNS), array_keys($columns))), 'PersonalData::COLUMNS names columns that are gone.');

        foreach (PersonalData::COLUMNS as $column => [$file, $why]) {
            $this->assertTrue(($file === null) !== ($why === null), "{$column}: either its export file or why it isn't exported.");
        }

        // Deleting the account deletes or anonymises: never refused, never left pointing at nobody.
        foreach ($columns as $column => $rule) {
            $this->assertContains($rule, ['CASCADE', 'SET NULL', 'NONE'], "{$column} would stop the account being deleted ({$rule}).");
        }

        $this->assertSame(['sessions.user_id', 'subscriptions.user_id'], array_keys(array_filter($columns, static fn (string $rule): bool => $rule === 'NONE')), 'A column pointing at people without a foreign key: DeleteAccount must remove its rows (as it does sessions and subscriptions).');
    }

    public function test_every_polymorphic_link_and_email_column_says_what_happens_to_it(): void
    {
        $database = DB::connection()->getDatabaseName();
        $found = [];

        // A *_type beside its *_id: it can name an account, or something of theirs.
        foreach (DB::select("SELECT a.TABLE_NAME AS t, SUBSTRING(a.COLUMN_NAME, 1, LENGTH(a.COLUMN_NAME) - 5) AS c FROM information_schema.COLUMNS a
            JOIN information_schema.COLUMNS b ON b.TABLE_SCHEMA = a.TABLE_SCHEMA AND b.TABLE_NAME = a.TABLE_NAME AND b.COLUMN_NAME = CONCAT(SUBSTRING(a.COLUMN_NAME, 1, LENGTH(a.COLUMN_NAME) - 5), '_id')
            WHERE a.TABLE_SCHEMA = ? AND a.COLUMN_NAME LIKE '%\\_type'", [$database]) as $row) {
            $found[] = $row->t.'.'.$row->c;
        }

        // An address outside users: it outlives the account unless something removes it.
        foreach (DB::select("SELECT TABLE_NAME AS t, COLUMN_NAME AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME <> 'users'
            AND DATA_TYPE IN ('varchar', 'text') AND COLUMN_NAME REGEXP '(^|_)email$'", [$database]) as $row) {
            $found[] = $row->t.'.'.$row->c;
        }

        sort($found);
        $this->assertSame(array_keys(PersonalData::INDIRECT), $found, 'A polymorphic link or an email column changed: add it to PersonalData::INDIRECT with what happens to it when the account goes (or drop one that is gone).');
    }

    public function test_deleting_the_account_removes_reports_on_them_and_links_waiting_at_their_address(): void
    {
        $ada = User::factory()->create(['adult_confirmed_at' => now(), 'email' => 'ada@example.com']);
        $reporter = User::factory()->create();
        Report::factory()->create(['reporter_id' => $reporter->id, 'reportable_type' => $ada->getMorphClass(), 'reportable_id' => $ada->id]);
        DB::table('password_reset_tokens')->insert(['email' => 'ada@example.com', 'token' => 'hashed', 'created_at' => now()]);
        SignInLink::factory()->create(['email' => 'ada@example.com']);
        SignInLink::factory()->create(['email' => 'grace@example.com']);

        app(DeleteAccount::class)->handle($ada, null);

        $this->assertSame(0, Report::query()->count());
        $this->assertSame(0, DB::table('password_reset_tokens')->count());
        $this->assertSame(['grace@example.com'], SignInLink::query()->pluck('email')->all());
    }

    public function test_every_column_of_the_account_is_exported_or_explained(): void
    {
        $columns = Schema::getColumnListing('users');
        sort($columns);
        $listed = array_keys(PersonalData::ACCOUNT);
        sort($listed);
        $this->assertSame($listed, $columns, 'A users column changed: add it to PersonalData::ACCOUNT with the export key it goes under, or why it does not.');

        Storage::fake('private');
        $ada = User::factory()->create(['adult_confirmed_at' => now()]);
        $account = $this->export($ada)['account.json'];
        foreach (array_unique(array_filter(PersonalData::ACCOUNT, 'is_string')) as $key) {
            $this->assertArrayHasKey($key, $account, "account.json has no {$key}.");
        }
        $this->assertSame(Terms::current(), $account['termsAccepted']['version']);
    }

    public function test_the_export_holds_every_file_the_list_names_and_todays_records(): void
    {
        Storage::fake('private');
        $ada = User::factory()->create(['adult_confirmed_at' => now()]);
        $this->todaysRecords($ada);

        $files = $this->export($ada);

        foreach (array_diff(array_unique(array_filter(array_column(PersonalData::COLUMNS, 0))), PersonalData::SOMETIMES) as $file) {
            $this->assertContains($file, array_keys($files), "The export has no {$file}.");
        }

        $this->assertSame('apple', $files['records/store-purchases.json'][0]['store']);
        $this->assertArrayNotHasKey('purchase_token', $files['records/store-purchases.json'][0]);
        $this->assertSame([1, 'ios'], [$files['records/app-devices.json'][0]['push'], $files['records/app-devices.json'][0]['platform']]);
        $this->assertSame('Our tune', $files['records/ideas.json'][0]['title']);
        $this->assertSame('tenor', $files['records/idea-voices.json'][0]['part']);
        $this->assertNotNull($files['records/idea-voices.json'][0]['file']);
        $this->assertSame('alto', $files['records/choir-voices.json'][0]['part']);
        $this->assertSame('Bring Him Home', $files['records/song-requests.json'][0]['title']);
        $this->assertCount(1, $files['records/song-request-votes.json']);
        $this->assertSame('instrument', $files['projects.json'][0]['parts'][0]['kind']);
        $this->assertSame(['I', 'IV', 'V', 'I'], $files['my-templates.json'][0]['versions'][0]['builder']['progression']['bars'][0] ?? null);
        $this->assertSame(['lessons' => ['u1-l1' => ['stars' => 3]]], $files['course-progress-before-reset.json']);
        $this->assertSame(false, $files['account.json']['appPushes']['types']['like']);
    }

    public function test_deleting_the_account_removes_todays_records_or_lets_them_go(): void
    {
        Storage::fake('private');
        $ada = User::factory()->create(['adult_confirmed_at' => now()]);
        $this->todaysRecords($ada);

        app(DeleteAccount::class)->handle($ada, null);

        $this->assertSame(0, AppDevice::query()->count());
        $this->assertSame(0, UserLearning::query()->count());
        $this->assertSame(0, Idea::query()->count());
        $this->assertSame(0, IdeaVoice::query()->where('user_id', $ada->id)->count());
        $this->assertSame(0, ChoirVoice::query()->count());
        $this->assertSame(0, SongRequestVote::query()->count());
        $this->assertSame(0, Project::query()->count());
        $this->assertSame(0, Template::query()->where('owner_id', $ada->id)->count());
        // The store's purchase stays, linked to nobody (a restore on another account links it there),
        // and a request stays for the others who want it, without why they asked.
        $this->assertNull(StoreSubscription::query()->sole()->user_id);
        $this->assertSame([null, null], [SongRequest::query()->sole()->user_id, SongRequest::query()->sole()->why]);
    }

    /**
     * One of each of today's records (docs/decisions.md, 2026-10-27) for them.
     */
    private function todaysRecords(User $ada): void
    {
        StoreSubscription::factory()->create(['user_id' => $ada->id, 'purchase_token' => 'secret-token']);
        $device = AppDevice::factory()->create(['user_id' => $ada->id, 'platform' => 'ios']);
        $device->forceFill(['push_service' => 'apns', 'push_token' => 'tok', 'push_token_hash' => hash('sha256', 'tok'), 'push_environment' => 'production'])->save();
        $ada->forceFill(['push_settings' => ['types' => ['like' => false], 'quietHours' => null]])->save();
        UserLearning::factory()->create(['user_id' => $ada->id, 'reset_snapshot' => ['lessons' => ['u1-l1' => ['stars' => 3]]], 'undo_until' => now()->addDays(30)]);

        $tune = Template::factory()->create(['owner_id' => $ada->id, 'title' => 'Our tune']);
        $data = (array) json_decode((string) file_get_contents(dirname(base_path()).'/web/templates/amazing-grace.json'), true);
        $data['builder'] = ['progression' => ['bars' => [['I', 'IV', 'V', 'I']]]];
        $version = TemplateVersion::factory()->create(['template_id' => $tune->id, 'data' => $data]);
        $tune->forceFill(['current_version_id' => $version->id])->save();
        $idea = Idea::factory()->create(['user_id' => $ada->id, 'template_id' => $tune->id]);
        IdeaVoice::factory()->create(['idea_id' => $idea->id, 'user_id' => $ada->id, 'part_key' => 'tenor']);

        ChoirVoice::factory()->create(['choir_id' => Choir::factory()->create()->id, 'user_id' => $ada->id, 'part_key' => 'alto']);
        $request = SongRequest::factory()->create(['user_id' => $ada->id, 'title' => 'Bring Him Home', 'why' => 'For my dad']);
        SongRequestVote::query()->forceCreate(['song_request_id' => $request->id, 'user_id' => $ada->id]);

        $project = Project::factory()->create(['user_id' => $ada->id]);
        Part::factory()->create(['project_id' => $project->id, 'kind' => 'instrument']);
    }

    /**
     * Their export, every file in it decoded.
     *
     * @return array<string, mixed>
     */
    private function export(User $user): array
    {
        $export = DataExport::factory()->create(['user_id' => $user->id, 'status' => 'preparing', 'file_key' => null]);
        (new BuildExport($export->id))->handle();

        $path = (string) tempnam(sys_get_temp_dir(), 'export');
        file_put_contents($path, (string) Storage::disk('private')->get((string) $export->refresh()->file_key));
        $zip = new ZipArchive;
        $zip->open($path);
        $files = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            $files[$name] = json_decode((string) $zip->getFromIndex($i), true);
        }

        $zip->close();
        @unlink($path);

        return $files;
    }
}
