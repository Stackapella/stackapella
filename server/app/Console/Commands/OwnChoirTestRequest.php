<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\OwnChoir\OwnChoirRequests;
use App\Enums\OwnChoirStatus;
use App\Exceptions\Refusal;
use App\Models\AuditLog;
use App\Models\OwnChoirFreeSong;
use App\Models\OwnChoirRequest;
use App\Models\Template;
use App\Models\User;
use App\Support\Ids;
use App\Support\OwnChoir\OwnChoir;
use App\Support\OwnChoir\SyntheticClip;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Staff's end-to-end test of Hear yourself as a choir on production (the coordinator, 2026-11-22): run on the server's
 * console, it makes a staff account's song exactly as the app would (consent recorded as a test, the request, its clip
 * uploaded to the request's own upload key, complete), with a synthetic clip and no person in it (SyntheticClip), so the
 * real worker claims it. --status prints how it's going, --cleanup takes it away with its files and the account's kept
 * voice. Staff accounts only, with own_choir on for them (app:flags:account); every step in the audit log.
 */
final class OwnChoirTestRequest extends Command
{
    protected $signature = 'app:own-choir:test-request {email? : a staff account} {--song=silent-night : a library song} {--part= : the part the clip sings (default its first)} {--own : the account\'s newest own song instead} {--status= : a request\'s id, to print it} {--cleanup= : a request\'s id, to delete it with its files and the voice}';

    protected $description = 'Make, show or delete a staff account\'s test song for Hear yourself as a choir, with a synthetic clip';

    public function handle(OwnChoirRequests $choir): int
    {
        if (is_string($this->option('status'))) {
            return $this->status((string) $this->option('status'));
        }

        $user = User::query()->where('email', strtolower(trim((string) $this->argument('email'))))->first();

        if (is_string($this->option('cleanup'))) {
            return $this->cleanup($choir, (string) $this->option('cleanup'));
        }

        if ($user === null || ! ($user->is_admin || $user->is_editor)) {
            $this->error('No staff account (admin or editor) with that email.');

            return self::FAILURE;
        }

        if (! OwnChoir::on($user)) {
            $this->error("own_choir is off for {$user->email}: turn it on for them first (php artisan app:flags:account own_choir {$user->email}).");

            return self::FAILURE;
        }

        $template = $this->option('own')
            ? Template::query()->with('currentVersion')->where('owner_id', $user->id)->whereNull('deleted_at')->latest('id')->first()
            : Template::query()->with('currentVersion')->whereNull('owner_id')->where('slug', (string) $this->option('song'))->first();
        $data = (array) ($template?->currentVersion->data ?? []);
        $part = is_string($this->option('part')) && $this->option('part') !== '' ? (string) $this->option('part') : (string) (collect((array) ($data['parts'] ?? []))->first()['key'] ?? '');

        if ($template === null || $part === '') {
            $this->error($this->option('own') ? 'That account has no own song with a part.' : 'No such library song.');

            return self::FAILURE;
        }

        if (! OwnChoir::consented($user)) {
            $choir->consent($user, OwnChoir::termsVersion());
            AuditLog::record('own_choir.test_consent', $user, null, reason: 'Console: staff test, consent recorded on their behalf');
        }

        ['wav' => $wav, 'offsetFrames' => $offset] = SyntheticClip::make($data, $part);

        try {
            ['request' => $request] = $choir->start($user, ['templateId' => (string) $template->slug, 'partKey' => $part, 'offsetFrames' => $offset, 'sampleRate' => 48000, 'frames' => intdiv(strlen($wav) - 44, 2), 'bytes' => strlen($wav), 'sha256' => hash('sha256', $wav)], null, staffTest: true);
            Storage::disk('private')->put((string) $request->upload_key, $wav);
            $request = $choir->complete($user, $request);
        } catch (Refusal $e) {
            $this->error('Refused: '.$e->refusalCode.' '.json_encode($e->extra));

            return self::FAILURE;
        }

        AuditLog::record('own_choir.test_request', $user, null, after: ['request' => $request->sqid(), 'song' => $template->slug, 'part' => $part], reason: 'Console: staff end-to-end test');
        $this->info("Made {$request->sqid()} ({$template->slug}, {$part}): {$request->status->value}. Follow it with --status={$request->sqid()}.");

        return self::SUCCESS;
    }

    private function status(string $sqid): int
    {
        $request = OwnChoirRequest::query()->with('parts')->find(Ids::decode($sqid) ?? 0);

        if ($request === null) {
            $this->error('No such request.');

            return self::FAILURE;
        }

        $this->line(json_encode([
            'id' => $request->sqid(), 'status' => $request->status->value, 'failure' => $request->failure, 'tries' => $request->tries,
            'partMinutes' => $request->part_minutes, 'gpuSeconds' => $request->gpu_seconds, 'claimedAt' => $request->claimed_at?->toIso8601ZuluString(),
            'readyAt' => $request->ready_at?->toIso8601ZuluString(),
            'parts' => $request->parts->map(static fn ($p): array => ['part' => $p->part_key, 'status' => $p->status, 'failure' => $p->failure, 'media' => $p->media_key !== null, 'listen' => $p->listen_key !== null])->values()->all(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '');

        return self::SUCCESS;
    }

    private function cleanup(OwnChoirRequests $choir, string $sqid): int
    {
        $request = OwnChoirRequest::query()->with('user')->find(Ids::decode($sqid) ?? 0);
        $user = $request?->user;

        if ($request === null || $user === null || ! ($user->is_admin || $user->is_editor)) {
            $this->error('No such request on a staff account.');

            return self::FAILURE;
        }

        if ($request->status !== OwnChoirStatus::Deleted) {
            $choir->delete($user, $request);
        }

        // A test leaves the account as it was: the row goes too (so it never counts toward their songs), and with it
        // the free song it took (the coordinator, 2026-11-22: a staff account's own free song is theirs to try).
        OwnChoirFreeSong::query()->where('own_choir_request_id', $request->id)->delete();
        OwnChoirRequest::query()->whereKey($request->id)->delete();
        OwnChoirRequests::dropVoices($user);
        AuditLog::record('own_choir.test_cleanup', $user, null, after: ['request' => $sqid], reason: 'Console: staff end-to-end test cleaned up');
        $this->info("Deleted {$sqid}, its files, its free song and {$user->email}'s kept voice.");

        return self::SUCCESS;
    }
}
