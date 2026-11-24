<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\OwnChoir\ChoirGalleryItems;
use App\Http\Controllers\Controller;
use App\Http\Requests\OwnChoir\Gallery\DecideGalleryRequest;
use App\Http\Requests\OwnChoir\Gallery\GalleryQueueRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * admin.choirGallery.list and .decide: staff hear each choir before the gallery shows it.
 */
final class ChoirGalleryController extends Controller
{
    public function index(GalleryQueueRequest $request, ChoirGalleryItems $items): JsonResponse
    {
        return response()->json(['data' => $items->queue((string) ($request->validated('status') ?? 'pending'))]);
    }

    public function decide(DecideGalleryRequest $request, string $item, ChoirGalleryItems $items, #[CurrentUser] User $admin): JsonResponse
    {
        /** @var array{decision: string, reason?: string|null, reasonText?: string|null} $data */
        $data = $request->validated();

        return response()->json(['data' => $items->decide($admin, $item, $data, $request->ip())]);
    }
}
