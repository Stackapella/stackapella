<?php

declare(strict_types=1);

namespace Tests\Feature\Groups;

use App\Actions\Groups\TrackScores;
use App\Models\AuditLog;
use App\Models\GroupSong;
use App\Models\SupportMessage;
use App\Models\Template;
use App\Models\TrackScore;
use App\Models\User;
use App\Support\Flags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * "Send us your song" (docs/choir-tracks-plan.md, Hillel 2026-11-11): a director sends the score's files (private,
 * checked by size, SHA-256 and their first bytes) with the request and their exact word on its rights; Admin reads
 * the files and gives the song from the request, which shares it into the group and tells the director on the group
 * page. Free, and never an email.
 */
final class SendUsYourSongTest extends TestCase
{
    use RefreshDatabase;

    private const PDF = "%PDF-1.7\n1 0 obj << >> endobj\n%%EOF\n";

    private const MUSICXML = "\xEF\xBB\xBF<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<!DOCTYPE score-partwise PUBLIC \"-//Recordare//DTD MusicXML 4.0 Partwise//EN\" \"http://www.musicxml.org/dtds/partwise.dtd\">\n<score-partwise version=\"4.0\"></score-partwise>\n";

    private const PNG = "\x89PNG\r\n\x1A\n\x00\x00\x00\rIHDR";

    private User $director;

    private string $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->director = User::factory()->create(['name' => 'Ana Ruiz', 'email' => 'ana@example.com', 'email_verified_at' => now()])->fresh() ?? throw new \RuntimeException('no user');
        $this->actingAs($this->director);
        $this->withPlus();
        $this->group = (string) $this->postJson('/api/groups', ['name' => 'Thursday Singers', 'adult' => true])->assertCreated()->json('data.id');
    }

    /**
     * Sends one file as the app does: start, PUT, complete.
     */
    private function send(string $bytes, string $type, string $name = 'Score.pdf', ?string $as = null): TestResponse
    {
        $started = $this->postJson('/api/groups/'.$this->group.'/tracks/scores', ['name' => $name, 'contentType' => $type, 'bytes' => strlen($as ?? $bytes), 'sha256' => hash('sha256', $as ?? $bytes)])->assertCreated();
        Storage::disk('private')->put((string) TrackScore::query()->latest('id')->value('upload_key'), $bytes);

        return $this->postJson('/api/groups/'.$this->group.'/tracks/scores/'.$started->json('data.id').'/complete');
    }

    public function test_a_director_sends_the_score_and_admin_gives_the_song_from_the_request(): void
    {
        $started = $this->postJson('/api/groups/'.$this->group.'/tracks/scores', ['name' => '../God So Loved the World.pdf', 'contentType' => 'application/pdf', 'bytes' => strlen(self::PDF), 'sha256' => hash('sha256', self::PDF)]);
        $this->assertMatchesContract($started, 'groups.tracks.scores.create', 201);
        $started->assertJsonPath('data.kind', 'pdf')->assertJsonPath('data.status', 'uploading')->assertJsonPath('data.name', 'God So Loved the World.pdf')->assertJsonPath('upload.headers.Content-Type', 'application/pdf');
        $pdf = (string) $started->json('data.id');

        // Nothing sent yet; then the file, checked.
        $this->assertMatchesContract($this->postJson('/api/groups/'.$this->group.'/tracks/scores/'.$pdf.'/complete'), 'groups.tracks.scores.complete', 409);
        $this->postJson('/api/groups/'.$this->group.'/tracks/scores', ['name' => 'a.pdf', 'contentType' => 'application/pdf', 'bytes' => strlen(self::PDF), 'sha256' => hash('sha256', self::PDF)])->assertCreated();
        $score = TrackScore::query()->latest('id')->firstOrFail();
        Storage::disk('private')->put($score->upload_key, self::PDF);
        $done = $this->postJson('/api/groups/'.$this->group.'/tracks/scores/'.$score->sqid().'/complete');
        $this->assertMatchesContract($done, 'groups.tracks.scores.complete', 200);
        $done->assertJsonPath('data.status', 'ready');
        // Again: the same answer.
        $this->postJson('/api/groups/'.$this->group.'/tracks/scores/'.$score->sqid().'/complete')->assertOk()->assertJsonPath('data.status', 'ready');
        $xml = (string) $this->send(self::MUSICXML, 'application/vnd.recordare.musicxml+xml', 'Score.musicxml')->assertOk()->json('data.id');

        // The request with both files and the rights sentence they confirmed, word for word.
        $this->travelTo(now()->setTime(10, 0));
        $asked = $this->postJson('/api/groups/'.$this->group.'/tracks', ['piece' => 'God So Loved the World', 'rights' => 'public-domain', 'rightsConfirmed' => true, 'scoreIds' => [$score->sqid(), $xml]]);
        $this->assertMatchesContract($asked, 'groups.tracks.create', 201);
        $message = SupportMessage::query()->sole();
        $this->assertSame(__('support.tracks.rights.public-domain'), $message->context['tracks']['rightsStatement'] ?? null);
        $this->assertSame(2, TrackScore::query()->where('support_message_id', $message->id)->count());

        // A file already in a request, or never completed, can't go in another.
        $this->postJson('/api/groups/'.$this->group.'/tracks', ['piece' => 'Again', 'rights' => 'public-domain', 'rightsConfirmed' => true, 'scoreIds' => [$xml]])->assertJsonValidationErrors('scoreIds');
        $this->postJson('/api/groups/'.$this->group.'/tracks', ['piece' => 'Again', 'rights' => 'public-domain', 'rightsConfirmed' => true, 'scoreIds' => [$pdf]])->assertJsonValidationErrors('scoreIds');

        $list = $this->getJson('/api/groups/'.$this->group.'/tracks');
        $this->assertMatchesContract($list, 'groups.tracks.list', 200);
        $list->assertJsonPath('data.0.status', 'received')->assertJsonPath('data.0.scores', 2)->assertJsonPath('data.0.song', null);

        // Admin: the files (a download link only on the message itself) and the rights record, ready for Give this song.
        $this->actingAsAdmin();
        $row = $this->getJson('/api/admin/support?category=tracks');
        $this->assertMatchesContract($row, 'admin.support.list', 200);
        $row->assertJsonPath('data.0.tracks.status', 'received')->assertJsonPath('data.0.tracks.scores.0.url', null)->assertJsonPath('data.0.tracks.scores.1.kind', 'musicxml');
        $shown = $this->getJson('/api/admin/support/'.$message->sqid());
        $this->assertMatchesContract($shown, 'admin.support.show', 200);
        $this->assertNotNull($shown->json('data.tracks.scores.0.url'));
        $rights = (array) $shown->json('data.tracks.give.rights');
        $this->assertSame(['confirmedBy' => 'Ana Ruiz <ana@example.com>, director of Thursday Singers', 'confirmedAt' => now()->utc()->toIso8601ZuluString(), 'wording' => __('support.tracks.rights.public-domain'), 'source' => 'Send us your song, request '.$message->sqid().' ('.now()->toDateString().')'], $rights);

        // Given from the request: the song is theirs, in the group, the request delivered and closed.
        $song = [...(array) json_decode((string) file_get_contents(base_path('../web/templates/amazing-grace.json')), true), 'id' => 'arranger-file', 'version' => 1, 'title' => 'God So Loved the World', 'reviewed' => true];
        $given = $this->postJson('/api/admin/people/'.$this->director->sqid().'/songs', ['song' => $song, 'rights' => $rights, 'trackRequest' => $message->sqid()]);
        $this->assertMatchesContract($given, 'admin.people.songs.create', 201);
        $template = Template::query()->where('owner_id', $this->director->id)->sole();
        $this->assertSame(1, GroupSong::query()->where('template_id', $template->id)->count());
        $this->assertSame([SupportMessage::CLOSED, 'delivered'], [$message->refresh()->status, $this->getJson('/api/admin/support/'.$message->sqid())->json('data.tracks.status')]);
        $this->assertSame(1, AuditLog::query()->where('action', 'support.tracks_delivered')->count());

        // Once is enough: the same request can't be delivered again.
        $this->postJson('/api/admin/people/'.$this->director->sqid().'/songs', ['song' => $song, 'rights' => $rights, 'trackRequest' => $message->sqid()])->assertJsonValidationErrors('trackRequest');
        $this->assertSame(1, Template::query()->where('owner_id', $this->director->id)->count());

        // The director's group page says it's ready.
        $this->actingAs($this->director);
        $this->getJson('/api/groups/'.$this->group.'/tracks')->assertJsonPath('data.0.status', 'delivered')->assertJsonPath('data.0.song.id', $template->slug)->assertJsonPath('data.0.song.title', 'God So Loved the World');
    }

    public function test_a_file_must_be_what_it_says_and_its_bytes_must_match(): void
    {
        // A PNG sent as a PDF is deleted and refused; a file that isn't the bytes promised, too.
        $refused = $this->send(self::PNG, 'application/pdf');
        $this->assertMatchesContract($refused, 'groups.tracks.scores.complete', 422);
        $refused->assertJsonPath('code', 'score_type');
        $this->assertMatchesContract($this->send(self::PDF, 'application/pdf', as: '%PDF-something else'), 'groups.tracks.scores.complete', 409);
        $this->assertSame(0, TrackScore::query()->where('status', TrackScore::READY)->count());

        // Each kind by its own first bytes.
        $this->assertTrue(TrackScores::looksLike('mxl', 'application/vnd.recordare.musicxml', "PK\x03\x04rest"));
        $this->assertTrue(TrackScores::looksLike('photo', 'image/jpeg', "\xFF\xD8\xFF\xE0"));
        $this->assertTrue(TrackScores::looksLike('photo', 'image/png', self::PNG));
        $this->assertTrue(TrackScores::looksLike('photo', 'image/heic', "\x00\x00\x00\x18ftypheic"));
        $this->assertTrue(TrackScores::looksLike('musicxml', 'application/vnd.recordare.musicxml+xml', '<score-timewise version="3.1">'));
        $this->assertFalse(TrackScores::looksLike('musicxml', 'application/vnd.recordare.musicxml+xml', '<html><body>score-partwise</body></html>'));
        $this->assertFalse(TrackScores::looksLike('photo', 'image/heic', "\x00\x00\x00\x18ftypmp42"));

        // Only the types a score comes as, at most 25 MB.
        $this->assertMatchesContract($this->postJson('/api/groups/'.$this->group.'/tracks/scores', ['name' => 'a.docx', 'contentType' => 'application/msword', 'bytes' => 10, 'sha256' => str_repeat('a', 64)]), 'groups.tracks.scores.create', 422);
        $this->postJson('/api/groups/'.$this->group.'/tracks/scores', ['name' => 'a.pdf', 'contentType' => 'application/pdf', 'bytes' => TrackScores::MAX_BYTES + 1, 'sha256' => str_repeat('a', 64)])->assertJsonValidationErrors('bytes');
    }

    public function test_only_the_director_sends_or_reads_and_sending_is_limited(): void
    {
        $member = User::factory()->create()->fresh() ?? throw new \RuntimeException('no user');
        $token = (string) str($this->postJson('/api/groups/'.$this->group.'/link')->assertCreated()->json('data.url'))->afterLast('/choir/');
        $body = ['name' => 'a.pdf', 'contentType' => 'application/pdf', 'bytes' => 10, 'sha256' => str_repeat('a', 64)];

        $this->actingAs($member);
        $this->postJson('/api/choir/'.$token, ['adult' => true])->assertSuccessful();
        $this->assertMatchesContract($this->postJson('/api/groups/'.$this->group.'/tracks/scores', $body), 'groups.tracks.scores.create', 403);
        $this->assertMatchesContract($this->getJson('/api/groups/'.$this->group.'/tracks'), 'groups.tracks.list', 403);
        $this->actingAs(User::factory()->create());
        $this->assertMatchesContract($this->postJson('/api/groups/'.$this->group.'/tracks/scores', $body), 'groups.tracks.scores.create', 404);
        $this->assertMatchesContract($this->getJson('/api/groups/'.$this->group.'/tracks'), 'groups.tracks.list', 404);

        // Someone else's file is theirs alone, even its complete.
        $this->actingAs($this->director);
        $id = (string) $this->postJson('/api/groups/'.$this->group.'/tracks/scores', $body)->assertCreated()->json('data.id');
        $this->actingAs($member);
        $this->assertMatchesContract($this->postJson('/api/groups/'.$this->group.'/tracks/scores/'.$id.'/complete'), 'groups.tracks.scores.complete', 403);
        $this->actingAs($this->director);
        $this->assertMatchesContract($this->postJson('/api/groups/'.$this->group.'/tracks/scores/nope/complete'), 'groups.tracks.scores.complete', 404);

        config(['limits.track-scores.account.max' => 2]);
        $this->postJson('/api/groups/'.$this->group.'/tracks/scores', $body)->assertCreated();
        $this->assertMatchesContract($this->postJson('/api/groups/'.$this->group.'/tracks/scores', $body), 'groups.tracks.scores.create', 429);
    }

    public function test_the_upload_link_holds_ten_minutes_and_the_uploads_switch_pauses_sending(): void
    {
        $this->travelTo(now()->startOfMinute());
        $started = $this->postJson('/api/groups/'.$this->group.'/tracks/scores', ['name' => 'a.pdf', 'contentType' => 'application/pdf', 'bytes' => strlen(self::PDF), 'sha256' => hash('sha256', self::PDF)])->assertCreated();
        $this->assertSame(now()->addMinutes(10)->utc()->toIso8601ZuluString(), $started->json('upload.expiresAt'));

        // The uploads switch (a storage attack) stops a file's start and its complete, as it does a take's.
        app(Flags::class)->set('pause_uploads', 'on', null, 'Storage attack');
        $this->postJson('/api/groups/'.$this->group.'/tracks/scores', ['name' => 'b.pdf', 'contentType' => 'application/pdf', 'bytes' => 10, 'sha256' => str_repeat('a', 64)])->assertStatus(423);
        $this->postJson('/api/groups/'.$this->group.'/tracks/scores/'.$started->json('data.id').'/complete')->assertStatus(423);
    }

    public function test_a_file_never_put_in_a_request_goes_after_a_day_and_one_in_a_request_stays(): void
    {
        $kept = (string) $this->send(self::PDF, 'application/pdf')->assertOk()->json('data.id');
        $this->send(self::PDF, 'application/pdf')->assertOk();
        $this->postJson('/api/groups/'.$this->group.'/tracks', ['piece' => 'Shenandoah', 'rights' => 'own-arrangement', 'rightsConfirmed' => true, 'scoreIds' => [$kept]])->assertCreated();
        $files = TrackScore::query()->pluck('file_key')->all();
        // Something put in the kept file's upload slot after its complete: no one checks it, so it doesn't stay.
        $slot = (string) TrackScore::query()->orderBy('id')->value('upload_key');
        Storage::disk('private')->put($slot, 'stray');

        $this->travel(25)->hours();
        $this->artisan('app:track-scores:prune')->expectsOutputToContain('Deleted 1 ')->assertSuccessful();

        $this->assertSame([$kept], TrackScore::query()->get()->map->sqid()->all());
        $this->assertSame([true, false], array_map(static fn (?string $key): bool => Storage::disk('private')->exists((string) $key), $files));
        $this->assertFalse(Storage::disk('private')->exists($slot));
    }
}
