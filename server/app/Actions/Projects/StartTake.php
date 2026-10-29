<?php

declare(strict_types=1);

namespace App\Actions\Projects;

use App\Exceptions\Refusal;
use App\Models\Part;
use App\Models\ProjectMember;
use App\Models\Score;
use App\Models\SyncTombstone;
use App\Models\Take;
use App\Models\User;
use App\Support\Storage\Uploads;
use App\Support\Sync\SyncSeq;
use App\Support\Trust\TrustLevel;
use App\Support\Usage\Plans;
use App\Support\Usage\UploadBytes;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * takes.create: record what the device made and hand back a presigned PUT for the WAV
 * (docs/backend-plan.md C4). Sending the same take again (an outbox retry) answers it as it is,
 * with a fresh upload while it's still waiting for one.
 */
final class StartTake
{
    public function __construct(private readonly Plans $plans, private readonly TrustLevel $trust, private readonly SyncSeq $seq, private readonly Uploads $uploads, private readonly UploadBytes $bytes) {}

    /**
     * @param  array{id: string, partId: string, sampleRate: int, frames: int, offsetFrames: int, bytes: int, sha256: string, scores?: array<string, mixed>|null}  $data
     * @return array{take: Take, upload: array{url: string, method: string, headers: array<string, string>, expiresAt: \DateTimeInterface}|null}
     */
    public function handle(User $user, array $data): array
    {
        $data['sha256'] = strtolower($data['sha256']);

        try {
            $take = DB::transaction(fn (): Take => $this->start($user, $data));
        } catch (UniqueConstraintViolationException) {
            // The same take sent twice at once: the other request made it.
            $take = DB::transaction(fn (): Take => $this->start($user, $data));
        }

        return ['take' => $take, 'upload' => $take->status === Take::UPLOADING ? $this->uploads->presign($take->upload_key, 'audio/wav', $take->upload_bytes, minutes: 10) : null];
    }

    /**
     * @param  array{id: string, partId: string, sampleRate: int, frames: int, offsetFrames: int, bytes: int, sha256: string, scores?: array<string, mixed>|null}  $data
     */
    private function start(User $user, array $data): Take
    {
        // A take counts toward the project's owner (a singer's included), whose account is locked.
        $ownerId = Part::query()->join('projects', 'projects.id', '=', 'parts.project_id')->where('parts.uuid', $data['partId'])->value('projects.user_id');
        $owner = $ownerId !== null ? User::query()->whereKey($ownerId)->firstOrFail() : $user;
        Owned::lockAccount($owner);
        $part = Part::query()->with('project')->where('uuid', $data['partId'])->lockForUpdate()->first();

        if ($part === null) {
            // A shared project's tombstones are on its owner's account.
            $accounts = [$user->id, ...ProjectMember::query()->join('projects', 'projects.id', '=', 'project_members.project_id')->where('project_members.user_id', $user->id)->pluck('projects.user_id')->all()];
            $gone = SyncTombstone::query()->whereIn('user_id', $accounts)->where('kind', 'part')->where('uuid', $data['partId'])->exists();

            throw new Refusal(409, $gone ? 'part_gone' : 'part_missing');
        }

        if (Owned::role($user, $part->project) === null) {
            throw new Refusal(404, 'not_found');
        }

        $existing = Take::query()->where('uuid', $data['id'])->first();

        if ($existing !== null) {
            if ($existing->user_id !== $user->id && $existing->recorded_by !== $user->id) {
                throw new Refusal(404, 'not_found');
            }

            $same = $existing->part_id === $part->id && $existing->sha256 === $data['sha256'] && $existing->frames === $data['frames'] && $existing->sample_rate === $data['sampleRate'] && $existing->upload_bytes === $data['bytes'];

            if (! $same) {
                throw ValidationException::withMessages(['id' => __('validation.take_id')]);
            }

            return $existing->setRelation('part', $part);
        }

        Owned::mayChange($user, $part, $part->project);
        Owned::live($part->project);

        $this->plans->ensureEditable($owner, $part->project);

        if ($this->trust->of($user) === TrustLevel::NEW) {
            throw new Refusal(403, 'email_unverified');
        }

        // The part's current take counts as gone, so re-recording at the limit works.
        $current = $part->current_take_id !== null ? Take::query()->whereKey($part->current_take_id)->whereNull('binned_at')->first() : null;

        if ($this->plans->storageUsed($owner) - ($current?->bytes() ?? 0) + $data['bytes'] > $this->plans->storageLimit($owner)) {
            throw new Refusal(403, 'storage_limit');
        }

        $this->bytes->count($user, $data['bytes']);

        $take = new Take([
            'sample_rate' => $data['sampleRate'],
            'frames' => $data['frames'],
            'offset_frames' => $data['offsetFrames'],
            'upload_bytes' => $data['bytes'],
            'sha256' => $data['sha256'],
        ]);
        $take->forceFill([
            'uuid' => $data['id'],
            // The project's account; who sang it is recorded_by.
            'user_id' => $owner->id,
            'recorded_by' => $user->id === $owner->id ? null : $user->id,
            'part_id' => $part->id,
            'project_id' => $part->project_id,
            'status' => Take::UPLOADING,
            'failure' => null,
            'stored_bytes' => null,
            'frames_per_peak' => 512,
            'peaks' => null,
            'binned_at' => null,
            // Random and immutable, never guessable from the person or the song.
            'upload_key' => 'uploads/takes/'.Str::lower(Str::random(32)).'.wav',
            'seq' => $this->seq->next($owner),
        ])->save();

        // The take's report joins the score history under the take's own id (shown with Plus).
        if (($data['scores'] ?? null) !== null) {
            $project = $part->project;
            $score = new Score(['template_slug' => $project->template_slug, 'template_version' => $project->template_version, 'part_key' => $part->template_part_key, 'recorded_at' => now(), 'summary' => $data['scores']]);
            $score->forceFill(['uuid' => $take->uuid, 'user_id' => $user->id, 'take_uuid' => $take->uuid])->save();
        }

        return $take->setRelation('part', $part);
    }
}
