<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Worker;

use App\Actions\AiVoices\AiCatalogue;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * worker.ai.catalogue: what every song still needs, for the catalogue runner.
 */
final class AiCatalogueController extends Controller
{
    public function __invoke(AiCatalogue $catalogue): JsonResponse
    {
        return response()->json($catalogue->handle(), 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    }
}
