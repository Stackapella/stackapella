<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListDisputesRequest;
use App\Http\Resources\Admin\PersonResource;
use App\Models\BillingDispute;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * admin.billing.disputes.list: chargebacks, newest first (Stripe's dispute process is followed in
 * Stripe's dashboard; this is where Hillel sees them).
 */
final class DisputesController extends Controller
{
    public function __invoke(ListDisputesRequest $request): JsonResponse
    {
        $page = BillingDispute::query()->orderByDesc('id')->cursorPaginate(50);
        $people = User::query()->whereIn('id', collect($page->items())->pluck('user_id')->filter())->get()->keyBy('id');

        return response()->json([
            'data' => collect($page->items())->map(fn (BillingDispute $d): array => [
                'id' => $d->sqid(),
                'person' => PersonResource::of($d->user_id !== null ? $people->get($d->user_id) : null),
                'amountCents' => $d->amount_cents,
                'currency' => $d->currency,
                'reason' => $d->reason,
                'status' => $d->status,
                'createdAt' => $d->created_at?->utc()->toIso8601ZuluString(),
            ])->all(),
            'meta' => ['nextCursor' => $page->nextCursor()?->encode()],
        ]);
    }
}
