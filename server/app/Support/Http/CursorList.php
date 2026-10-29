<?php

declare(strict_types=1);

namespace App\Support\Http;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A list in the contract's envelope: { "data": [...], "meta": { "nextCursor": ... } }
 * (contract/schemas/meta.json), rather than Laravel's own links and meta.
 */
final class CursorList
{
    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  CursorPaginator<int, TModel>  $page
     * @param  class-string<JsonResource>  $resource
     */
    public static function response(CursorPaginator $page, string $resource, Request $request): JsonResponse
    {
        return $resource::collection($page->items())
            ->additional(['meta' => ['nextCursor' => $page->nextCursor()?->encode()]])
            ->toResponse($request);
    }
}
