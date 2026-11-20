<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Worker;

use App\Actions\AiVoices\CompleteAiUpload;
use App\Actions\AiVoices\StartAiUpload;
use App\Http\Controllers\Controller;
use App\Http\Requests\Worker\CreateAiUploadRequest;
use App\Http\Resources\Worker\AiUploadResource;
use App\Models\AiUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * worker.ai.uploads.*: an AI master sent by the batch renderer, checked and published as the part's AI guide.
 */
final class AiUploadsController extends Controller
{
    public function store(CreateAiUploadRequest $request, StartAiUpload $start): JsonResponse
    {
        /** @var array{versionId: string, part: string, voice: string, sampleRate: int, frames: int, bytes: int, sha256: string, checks: array<string, mixed>, renderer: array<string, mixed>} $master */
        $master = $request->validated();
        ['upload' => $upload, 'put' => $put] = $start->handle($master);

        return response()->json([
            'data' => (new AiUploadResource($upload))->resolve(),
            'upload' => [...$put, 'expiresAt' => Carbon::instance($put['expiresAt'])->utc()->toIso8601ZuluString()],
        ], 201);
    }

    public function complete(AiUpload $upload, CompleteAiUpload $complete): AiUploadResource
    {
        return new AiUploadResource($complete->handle($upload));
    }

    public function show(AiUpload $upload): AiUploadResource
    {
        return new AiUploadResource($upload);
    }
}
