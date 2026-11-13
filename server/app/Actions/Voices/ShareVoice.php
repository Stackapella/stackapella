<?php

declare(strict_types=1);

namespace App\Actions\Voices;

use App\Actions\Counts\RecordCounts;
use App\Enums\CreditMode;
use App\Enums\SubmissionStatus;
use App\Exceptions\Refusal;
use App\Jobs\ProcessSubmission;
use App\Models\BetaAssignment;
use App\Models\BetaSinger;
use App\Models\BetaSubmission;
use App\Models\Take;
use App\Models\Template;
use App\Models\User;
use App\Services\Audio\Ffmpeg;
use App\Support\Accounts\Age;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * voices.share (docs/open-guide-voices-plan.md, "The recording ladder"; Hillel: "they should only need legal stuff if
 * they then later decide to share it"): a take the singer already kept, offered as a part's guide voice. The volunteers'
 * agreement is accepted here (its version, the time, the credit, the 18+ answer); the take becomes a submission in
 * Hillel's queue (or held until the account's email is confirmed). Several singers may offer the same part. Behind
 * lend_voice.
 */
final class ShareVoice
{
    /** Shared takes a day a person; recording on the device is never limited. */
    public const PER_DAY = 10;

    public function __construct(private readonly LendVoice $lend, private readonly Ffmpeg $ffmpeg) {}

    /**
     * @param  array{takeId: string, templateId: string, templateVersion: int, partKey: string, credit: string, linksProfile: bool, agreementVersion: int, adult: bool}  $input
     */
    public function handle(User $user, array $input): BetaSubmission
    {
        $this->lend->ensureOpen();
        if ($user->isSuspended()) {
            throw new Refusal(403, 'suspended');
        }
        if ($user->adult_confirmed_at === null) {
            Age::ensureMayClaimAdult($user);
            if (! $input['adult']) {
                throw new Refusal(403, 'adult_required');
            }
            $user->forceFill(['adult_confirmed_at' => now()])->save();
        }
        if ($input['agreementVersion'] !== (int) config('beta.volunteer_agreement_version')) {
            throw new Refusal(409, 'agreement_changed');
        }

        // The take: the singer's own, kept and ready, of this song version's part, sung in the song's key.
        $take = Take::query()->with(['part.project'])->where('uuid', $input['takeId'])->whereNull('binned_at')
            ->where(fn ($q) => $q->where('recorded_by', $user->id)->orWhere(fn ($q) => $q->whereNull('recorded_by')->where('user_id', $user->id)))->first();
        $template = Template::query()->official()->listed()->where('slug', $input['templateId'])->first();
        $version = $template?->versions()->where('version', $input['templateVersion'])->first();
        $project = $take?->part?->project;
        if ($take === null || $take->status !== 'ready' || $take->file_key === null || $template === null || $version === null || $project === null
            || $project->template_slug !== $template->slug || (int) $project->template_version !== $version->version
            || $take->part->template_part_key !== $input['partKey']
            || $project->music_key !== (string) ($version->data['key'] ?? '')) {
            throw new Refusal(404, 'not_found');
        }

        if (BetaSubmission::query()->where('take_id', $take->id)->exists()) {
            throw new Refusal(409, 'already_sent');
        }

        $master = $this->master($take);

        try {
            $sent = DB::transaction(function () use ($user, $input, $take, $template, $version, $master): BetaSubmission {
                $singer = BetaSinger::query()->where('user_id', $user->id)->lockForUpdate()->first();
                if ($singer?->volunteer_blocked_at !== null) {
                    throw new Refusal(403, 'volunteer_blocked');
                }
                // An invited (paid) beta singer records what Hillel assigns them.
                if ($singer !== null && ! $singer->volunteer) {
                    throw new Refusal(409, 'beta_singer');
                }
                if (BetaSubmission::query()->where('take_id', $take->id)->lockForUpdate()->exists()) {
                    throw new Refusal(409, 'already_sent');
                }
                if ($singer !== null && BetaSubmission::query()->whereNotNull('take_id')->where('created_at', '>=', now()->subDay())
                    ->whereIn('beta_assignment_id', BetaAssignment::query()->where('beta_singer_id', $singer->id)->select('id'))->count() >= self::PER_DAY) {
                    throw new Refusal(429, 'rate_limited', ['retryAfter' => 3600]);
                }

                $singer ??= new BetaSinger;
                $singer->forceFill([
                    'user_id' => $user->id, 'name' => $user->name, 'volunteer' => true, 'accepted_at' => $singer->accepted_at ?? now(),
                    'token_hash' => $singer->token_hash ?? hash('sha256', Str::random(40)),
                    'consent_version' => $input['agreementVersion'], 'consented_at' => now(),
                    'credit_mode' => CreditMode::from($input['credit'] === 'anonymous' ? 'anonymous' : 'name'), 'credit_links_profile' => $input['linksProfile'],
                    'revoked_at' => null,
                ])->save();

                // An open take, not a claim: it holds nothing back from anyone else singing the part.
                $assignment = BetaAssignment::query()->where('beta_singer_id', $singer->id)->where('template_id', $template->id)->where('part_key', $input['partKey'])->first()
                    ?? tap(new BetaAssignment, fn (BetaAssignment $a) => $a->forceFill(['beta_singer_id' => $singer->id, 'template_id' => $template->id, 'part_key' => $input['partKey']])->save());

                $held = $user->email_verified_at === null;
                $submission = new BetaSubmission;
                $submission->forceFill([
                    'beta_assignment_id' => $assignment->id, 'take_id' => $take->id, 'template_version_id' => $version->id, 'part_key' => $input['partKey'],
                    'status' => $held ? SubmissionStatus::Held : SubmissionStatus::Processing, 'music_key' => (string) ($version->data['key'] ?? ''),
                    'sample_rate' => $take->sample_rate, 'frames' => $master['frames'], 'latency_frames' => 0, 'bytes' => $master['bytes'], 'sha256' => $master['sha256'],
                    'device' => null, 'mic' => null, 'scores' => null, 'master_key' => $master['key'],
                    // The take's own place on the song's timeline (its offset and the part's nudge), as Hillel's nudge.
                    'offset_ms' => (int) round(($take->offset_frames + (int) ($take->part->nudge_frames ?? 0)) * 1000 / max(1, $take->sample_rate)),
                ])->save();

                return $submission;
            });
            if ($sent->status === SubmissionStatus::Processing) {
                ProcessSubmission::dispatch($sent->id);
            }
            // A volunteer's take sent (the recording ladder's count; anonymous).
            app(RecordCounts::class)->server('take_sent', true, null);

            return $sent;
        } catch (\Throwable $e) {
            Storage::disk('private')->delete($master['key']);

            throw $e;
        }
    }

    /**
     * Held takes whose accounts have now confirmed their email: sent to processing (scheduled; any way of confirming).
     */
    public static function releaseHeld(): int
    {
        $released = 0;
        BetaSubmission::query()->where('status', SubmissionStatus::Held->value)
            ->whereHas('assignment.singer.user', fn ($q) => $q->whereNotNull('email_verified_at'))
            ->pluck('id')->each(function (mixed $id) use (&$released): void {
                if (BetaSubmission::query()->whereKey($id)->where('status', SubmissionStatus::Held->value)->update(['status' => SubmissionStatus::Processing->value]) === 1) {
                    ProcessSubmission::dispatch((int) $id);
                    $released++;
                }
            });

        return $released;
    }

    /**
     * The take's FLAC decoded back to the master WAV (lossless), stored privately as the beta takes' masters are.
     *
     * @return array{key: string, frames: int, bytes: int, sha256: string}
     */
    private function master(Take $take): array
    {
        $flac = (string) tempnam(sys_get_temp_dir(), 'shared');
        $wav = $flac.'.wav';
        file_put_contents($flac, Storage::disk('private')->readStream((string) $take->file_key));

        try {
            $this->ffmpeg->decodeToWav($flac, $wav);
            $bytes = (int) filesize($wav);
            $key = 'beta/masters/'.Str::lower(Str::random(32)).'.wav';
            $stream = fopen($wav, 'rb');
            if ($stream === false) {
                throw new \RuntimeException("Couldn't open the master at {$wav}.");
            }
            Storage::disk('private')->put($key, $stream);
            fclose($stream);

            return ['key' => $key, 'frames' => intdiv($bytes - 44, 2), 'bytes' => $bytes, 'sha256' => (string) hash_file('sha256', $wav)];
        } finally {
            @unlink($flac);
            @unlink($wav);
        }
    }
}
