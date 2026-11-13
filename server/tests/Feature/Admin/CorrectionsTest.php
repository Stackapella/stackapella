<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Correction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Readers' corrections (docs/corrections-plan.md; Hillel, 2026-11-12): anyone sends one from a public page, nothing
 * about them is kept, and the team reads, accepts, rejects (saying why) and marks them done in Inbox > Corrections.
 */
final class CorrectionsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private static function body(array $with = []): array
    {
        return [
            'kind' => 'fact',
            'text' => 'John Newton wrote the words in 1772, not 1779.',
            'source' => 'https://hymnary.org/text/amazing_grace_how_sweet_the_sound',
            'page' => ['type' => 'song', 'path' => '/songs/amazing-grace', 'title' => 'Amazing Grace'],
            'lang' => 'en',
            'nickname' => '',
            ...$with,
        ];
    }

    public function test_anyone_sends_a_correction_and_nothing_about_them_is_kept(): void
    {
        $sent = $this->postJson('/api/corrections', self::body());
        $this->assertMatchesContract($sent, 'corrections.create', 201);
        $row = Correction::query()->firstOrFail();
        $this->assertSame($row->sqid(), $sent->json('data.id'));
        $this->assertSame(['fact', 'new', '/songs/amazing-grace', 'Amazing Grace', 'en'], [$row->kind->value, $row->state->value, $row->page_path, $row->page_title, $row->lang]);

        // Signed in, still nothing about them: the table has no column for it.
        $this->actingAs(User::factory()->create())->postJson('/api/corrections', self::body(['kind' => 'notes']))->assertCreated();
        $columns = array_keys((array) Correction::query()->latest('id')->firstOrFail()->getAttributes());
        $this->assertSame([], array_values(array_intersect($columns, ['user_id', 'email', 'ip', 'ip_hash', 'account_id'])));
    }

    public function test_a_bad_correction_names_its_field_and_a_bot_is_turned_away(): void
    {
        $bad = $this->postJson('/api/corrections', self::body(['text' => '']));
        $this->assertMatchesContract($bad, 'corrections.create', 422);
        $bad->assertJsonValidationErrors(['text']);
        $this->postJson('/api/corrections', self::body(['source' => 'javascript:alert(1)']))->assertJsonValidationErrors(['source']);
        $this->postJson('/api/corrections', self::body(['page' => ['type' => 'song', 'path' => 'https://elsewhere.example.com/x', 'title' => 'X']]))->assertJsonValidationErrors(['page.path']);
        $this->postJson('/api/corrections', self::body(['kind' => 'rant']))->assertJsonValidationErrors(['kind']);
        $this->postJson('/api/corrections', self::body(['nickname' => 'bot']))->assertJsonValidationErrors(['nickname']);
        $this->assertSame(0, Correction::query()->count());
    }

    public function test_the_team_reads_accepts_rejects_with_a_note_and_marks_done(): void
    {
        $fact = Correction::factory()->create(['created_at' => now()->subMinute()]);
        $notes = Correction::factory()->create(['kind' => 'notes', 'page_type' => 'part', 'page_path' => '/songs/silent-night/alto', 'page_title' => 'Silent Night, Alto']);
        $this->actingAsAdmin(User::factory()->admin()->create(['name' => 'Hillel Coren']));

        $list = $this->getJson('/api/admin/corrections');
        $this->assertMatchesContract($list, 'admin.corrections.list', 200);
        $list->assertJsonPath('data.0.id', $notes->sqid())->assertJsonPath('meta.counts', ['new' => 2, 'accepted' => 0, 'done' => 0, 'rejected' => 0]);
        $this->getJson('/api/admin/corrections?kind=notes')->assertJsonCount(1, 'data');
        $this->getJson('/api/admin/overview')->assertJsonPath('data.corrections.new', 2);

        $accepted = $this->patchJson('/api/admin/corrections/'.$fact->sqid(), ['state' => 'accepted', 'note' => 'Checked against the 1779 printing.']);
        $this->assertMatchesContract($accepted, 'admin.corrections.update', 200);
        $accepted->assertJsonPath('data.decision.by.name', 'Hillel Coren')->assertJsonPath('data.decision.state', 'accepted');
        $this->patchJson('/api/admin/corrections/'.$fact->sqid(), ['state' => 'done'])->assertOk()->assertJsonPath('data.state', 'done');
        $this->assertMatchesContract($this->patchJson('/api/admin/corrections/'.$fact->sqid(), ['state' => 'accepted']), 'admin.corrections.update', 409);
        $this->assertMatchesContract($this->patchJson('/api/admin/corrections/'.$fact->sqid(), ['state' => 'rejected', 'note' => 'late']), 'admin.corrections.update', 409);

        $noNote = $this->patchJson('/api/admin/corrections/'.$notes->sqid(), ['state' => 'rejected']);
        $this->assertMatchesContract($noNote, 'admin.corrections.update', 422);
        $noNote->assertJsonValidationErrors(['note']);
        $this->patchJson('/api/admin/corrections/'.$notes->sqid(), ['state' => 'rejected', 'note' => 'The sheet has a B.'])->assertOk();
        $this->assertMatchesContract($this->patchJson('/api/admin/corrections/nope', ['state' => 'accepted']), 'admin.corrections.update', 404);
        $this->getJson('/api/admin/corrections?state=rejected')->assertJsonCount(1, 'data')->assertJsonPath('meta.counts.done', 1);
        $this->getJson('/api/admin/overview')->assertJsonPath('data.corrections.new', 0);
    }

    public function test_only_the_team_reads_them(): void
    {
        $this->actingAs(User::factory()->create());
        $this->getJson('/api/admin/corrections')->assertForbidden();
    }
}
