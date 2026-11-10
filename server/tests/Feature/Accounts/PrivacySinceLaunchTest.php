<?php

declare(strict_types=1);

namespace Tests\Feature\Accounts;

use App\Actions\Users\DeleteAccount;
use App\Jobs\BuildExport;
use App\Models\BetaAssignment;
use App\Models\BetaSinger;
use App\Models\BetaSubmission;
use App\Models\CourseClip;
use App\Models\DataExport;
use App\Models\Report;
use App\Models\SchoolClass;
use App\Models\SchoolRedemption;
use App\Models\Template;
use App\Models\TemplateGuide;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/**
 * The privacy check of what came after 30 October (2026-11-02): Lend your voice, asking a singer
 * again, sheet music import and the school offer are in a person's export, and go (or lose what
 * names them) with their account, as the privacy page says.
 */
final class PrivacySinceLaunchTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A volunteer who claimed a part, was asked for it again, and had a take refused for abuse.
     *
     * @return array{0: User, 1: BetaSinger, 2: BetaAssignment, 3: BetaSubmission}
     */
    private function volunteer(): array
    {
        $ana = User::factory()->create(['adult_confirmed_at' => now()]);
        $singer = BetaSinger::factory()->accepted($ana)->consented()->create(['volunteer' => true, 'credit_links_profile' => true, 'volunteer_blocked_at' => now()]);
        $assignment = BetaAssignment::factory()->create(['beta_singer_id' => $singer->id, 'part_key' => 'alto']);
        $assignment->forceFill(['claimed_at' => now()->subDay(), 'expires_at' => now()->addDays(6), 'asked_again_at' => now(), 'asked_again_note' => 'The tune moved in bar 3.'])->save();
        $take = BetaSubmission::factory()->create(['beta_assignment_id' => $assignment->id, 'admin_note' => 'Too quiet.']);

        return [$ana, $singer, $assignment, $take];
    }

    /**
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
            $files[(string) $zip->getNameIndex($i)] = json_decode((string) $zip->getFromIndex($i), true);
        }

        $zip->close();
        @unlink($path);

        return $files;
    }

    public function test_the_export_holds_a_volunteers_claims_our_notes_and_a_songs_rights_confirmation(): void
    {
        Storage::fake('private');
        [$ana] = $this->volunteer();
        Template::factory()->create(['owner_id' => $ana->id])->forceFill(['imported_at' => now(), 'rights_confirmed_at' => now(), 'rights_statement' => '1'])->save();

        $files = $this->export($ana);
        $beta = $files['beta-singer.json'];
        $this->assertSame([true, true], [$beta['volunteer'], $beta['creditLinksYourProfile']]);
        $this->assertNotNull($beta['claimingStoppedAt']);
        $part = $beta['parts'][0];
        $this->assertNotNull($part['claimedAt']);
        $this->assertNotNull($part['heldUntil']);
        $this->assertSame('The tune moved in bar 3.', $part['askedAgain']['note']);
        $this->assertSame('Too quiet.', $part['takes'][0]['ourNote']);
        $this->assertSame('1', $files['my-templates.json'][0]['fromSheetMusic']['rightsWording']);
        $this->assertNotNull($files['my-templates.json'][0]['fromSheetMusic']['rightsConfirmedAt']);
    }

    public function test_a_volunteers_notes_and_the_reports_on_their_voices_go_with_the_account(): void
    {
        Storage::fake('private');
        Storage::fake('media');
        [$ana, $singer, $assignment, $take] = $this->volunteer();
        $guide = TemplateGuide::factory()->create(['beta_submission_id' => $take->id]);
        $clip = CourseClip::factory()->create(['beta_submission_id' => $take->id, 'media_key' => 'clips/ana.m4a', 'sample_rate' => 48000, 'frames' => 4800, 'published_at' => now()]);
        Report::factory()->create(['reportable_type' => 'template_guide', 'reportable_id' => $guide->id]);
        Report::factory()->create(['reportable_type' => 'course_clip', 'reportable_id' => $clip->id]);
        $other = Report::factory()->create(['reportable_type' => 'template_guide', 'reportable_id' => TemplateGuide::factory()->create()->id]);

        app(DeleteAccount::class)->handle($ana, null);

        $this->assertNull($assignment->refresh()->asked_again_note);
        $this->assertFalse($singer->refresh()->credit_links_profile);
        $this->assertNull($take->refresh()->admin_note);
        $this->assertSame([$other->id], Report::query()->pluck('id')->all());
        $this->assertNull($clip->refresh()->media_key);
    }

    public function test_a_teachers_codes_close_and_lose_their_names_and_students_keep_their_year(): void
    {
        $teacher = User::factory()->create();
        $open = SchoolClass::factory()->create(['teacher_id' => $teacher->id, 'name' => 'Ms Rivera, period 3']);
        $closed = SchoolClass::factory()->create(['teacher_id' => $teacher->id, 'name' => 'Ms Rivera, old', 'closed_at' => now()->subWeek(), 'closed_by' => 'admin']);
        $student = User::factory()->create(['school_plus_until' => now()->addMonths(9)]);
        SchoolRedemption::factory()->create(['school_class_id' => $open->id, 'user_id' => $student->id]);

        app(DeleteAccount::class)->handle($teacher, null);

        $open->refresh();
        $this->assertSame([__('admin.school.deleted_class'), 'teacher', null], [$open->name, $open->closed_by, $open->teacher_id]);
        $this->assertNotNull($open->closed_at);
        // One closed earlier keeps who closed it, and loses its name too.
        $this->assertSame([__('admin.school.deleted_class'), 'admin'], [$closed->refresh()->name, $closed->closed_by]);
        $this->assertSame(1, SchoolRedemption::query()->where('user_id', $student->id)->count());
        $this->assertNotNull($student->refresh()->school_plus_until);
    }
}
