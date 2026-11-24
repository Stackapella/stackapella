<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\OwnChoir\Gallery;

use App\Actions\OwnChoir\ChoirGalleryItems;
use App\Http\Controllers\Controller;
use App\Http\Requests\OwnChoir\Gallery\AddToGalleryRequest;
use App\Models\User;
use App\Support\OwnChoir\ChoirGallery;
use App\Support\Rights\RightsHere;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * ownChoir.gallery.add, .remove, .list and .show: the choir gallery (docs/choir-gallery-plan.md), behind choir_gallery.
 */
final class ChoirGalleryController extends Controller
{
    public function add(AddToGalleryRequest $request, string $song, ChoirGalleryItems $items, #[CurrentUser] User $user): JsonResponse
    {
        /** @var array{harmonyId: string, consentVersion: int, wordsHash: string, locale: string} $data */
        $data = $request->validated();

        return response()->json(['data' => ChoirGallery::entry($items->add($user, $song, $data))], 201);
    }

    public function remove(string $song, ChoirGalleryItems $items, #[CurrentUser] User $user): Response
    {
        $items->remove($user, $song);

        return response()->noContent();
    }

    public function index(Request $request, ChoirGalleryItems $items): JsonResponse
    {
        return response()->json(['data' => $items->list(RightsHere::country($request))]);
    }

    public function show(Request $request, string $item, ChoirGalleryItems $items): JsonResponse
    {
        return response()->json(['data' => $items->show($item, RightsHere::country($request))]);
    }
}
