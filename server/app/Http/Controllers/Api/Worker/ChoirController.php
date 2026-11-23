<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Worker;

use App\Actions\Growth\ChoirLive;
use App\Actions\OwnChoir\ChoirJobs;
use App\Actions\OwnChoir\RecordChoirWorkerStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Worker\ChoirStatusRequest;
use App\Http\Requests\Worker\CreateChoirPartRequest;
use App\Http\Requests\Worker\CreateChoirReferenceRequest;
use App\Http\Requests\Worker\ExtendChoirJobRequest;
use App\Http\Requests\Worker\FinishChoirJobRequest;
use App\Http\Resources\Worker\ChoirJobResource;
use App\Models\OwnChoirPart;
use App\Models\OwnChoirRequest;
use App\Models\OwnChoirVoice;
use App\Services\Audio\Ffmpeg;
use App\Support\Ids;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * worker.choir.*: the renderer's side of Hear yourself as a choir (track B phase 3).
 */
final class ChoirController extends Controller
{
    public function claim(ChoirJobs $jobs): JsonResponse|Response
    {
        // The worker's sign of life for Admin > Choir: it asks for work every few seconds, a job or not.
        Cache::put(ChoirLive::POLL, now()->utc()->toIso8601ZuluString(), now()->addDay());
        $job = $jobs->claim();

        if ($job === null) {
            return response()->noContent();
        }

        return response()->json(['data' => (new ChoirJobResource($job->load(['templateVersion.template', 'voice'])))->resolve()], 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    }

    public function startPart(CreateChoirPartRequest $request, OwnChoirRequest $job, ChoirJobs $jobs): JsonResponse
    {
        /** @var array{part: string, sampleRate: int, frames: int, bytes: int, sha256: string, checks: array<string, mixed>, renderer: array<string, mixed>} $master */
        $master = $request->validated();
        ['part' => $part, 'put' => $put] = $jobs->startPart($job->load('templateVersion'), (string) $request->validated('claim'), $master);

        return response()->json(['data' => self::part($part), 'upload' => [...$put, 'expiresAt' => Carbon::instance($put['expiresAt'])->utc()->toIso8601ZuluString()]], 201);
    }

    public function startReference(CreateChoirReferenceRequest $request, OwnChoirRequest $job, ChoirJobs $jobs): JsonResponse
    {
        /** @var array{sampleRate: int, frames: int, bytes: int, sha256: string, meta: array<string, mixed>, octave?: int|null, sungRange?: list<int>|null} $reference */
        $reference = $request->validated();
        ['voice' => $voice, 'put' => $put] = $jobs->startReference($job->load('templateVersion'), (string) $request->validated('claim'), $reference);

        return response()->json(['data' => self::reference($voice, $job->id), 'upload' => [...$put, 'expiresAt' => Carbon::instance($put['expiresAt'])->utc()->toIso8601ZuluString()]], 201);
    }

    public function completeReference(OwnChoirVoice $reference, ChoirJobs $jobs, Ffmpeg $ffmpeg): JsonResponse
    {
        $job = OwnChoirRequest::query()->where('own_choir_voice_id', $reference->id)->orderByDesc('id')->value('id');

        return response()->json(['data' => self::reference($jobs->completeReference($reference, $ffmpeg), is_int($job) ? $job : 0)]);
    }

    public function completePart(OwnChoirPart $part, ChoirJobs $jobs): JsonResponse
    {
        return response()->json(['data' => self::part($jobs->completePart($part))]);
    }

    public function finish(FinishChoirJobRequest $request, OwnChoirRequest $job, ChoirJobs $jobs): JsonResponse
    {
        $failed = $request->validated('failed');
        $gpu = $request->validated('gpuSeconds');
        /** @var array{detail?: string|null, level?: string|null, partTries?: list<array{partKey: string, tries: int}>|null, gateFailures?: list<array{partKey: string, reason?: string|null}>|null} $report */
        $report = $request->safe()->only(['detail', 'level', 'partTries', 'gateFailures']);
        $done = $jobs->finish($job->load('templateVersion'), (string) $request->validated('claim'), is_string($failed) ? $failed : null, is_numeric($gpu) ? (float) $gpu : null, $report);

        return response()->json(['data' => ['id' => $done->sqid(), 'status' => $done->status->value, 'failure' => $done->failure]]);
    }

    public function extend(ExtendChoirJobRequest $request, OwnChoirRequest $job, ChoirJobs $jobs): JsonResponse
    {
        return response()->json(['data' => ['claimSeconds' => $jobs->extend($job, (string) $request->validated('claim'), (float) $request->validated('gpuSeconds'))]]);
    }

    public function status(ChoirStatusRequest $request, RecordChoirWorkerStatus $record): Response
    {
        /** @var array{paused: string|null, spentTodayUsd: float|int, spentMonthUsd: float|int, limitDayUsd: float|int, limitMonthUsd: float|int} $status */
        $status = $request->validated();
        $record->handle($status);

        return response()->noContent();
    }

    /**
     * @return array{id: string, job: string, status: string}
     */
    private static function reference(OwnChoirVoice $voice, int $job): array
    {
        return ['id' => $voice->sqid(), 'job' => Ids::encode($job), 'status' => $voice->status];
    }

    /**
     * @return array{id: string, job: string, part: string, status: string, failure: string|null}
     */
    private static function part(OwnChoirPart $part): array
    {
        return ['id' => $part->sqid(), 'job' => Ids::encode($part->own_choir_request_id), 'part' => $part->part_key, 'status' => $part->status, 'failure' => $part->failure];
    }
}
