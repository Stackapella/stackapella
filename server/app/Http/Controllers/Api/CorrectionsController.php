<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Corrections\SubmitCorrection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Corrections\StoreCorrectionRequest;
use Illuminate\Http\JsonResponse;

/**
 * corrections.create: the public "Suggest a correction" form (docs/corrections-plan.md). Anyone, no session; nothing
 * about the reader is kept.
 */
final class CorrectionsController extends Controller
{
    public function __invoke(StoreCorrectionRequest $request, SubmitCorrection $submit): JsonResponse
    {
        /** @var array{kind: string, text: string, source?: string|null, page: array{type: string, path: string, title: string}, lang: string} $input */
        $input = $request->safe()->only(['kind', 'text', 'source', 'page', 'lang']);

        return response()->json(['data' => ['id' => $submit->handle($input)->sqid()]], 201);
    }
}
