<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Billing;

use App\Actions\Billing\StoreSubscriptions;
use App\Exceptions\Refusal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\StoreSyncRequest;
use App\Models\User;
use App\Support\Billing\BillingState;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * billing.store.sync (docs/app-purchases-plan.md): an app's App Store or Google Play purchase, or
 * its restore, linked to the account. Answers billing.show, also with the 409 when the purchases
 * are another account's, so the app shows where it stands without asking again.
 */
final class StoreController extends Controller
{
    public function sync(StoreSyncRequest $request, StoreSubscriptions $stores, BillingState $state, #[CurrentUser] User $user): JsonResponse
    {
        /** @var list<string> $transactions */
        $transactions = array_values((array) $request->validated('transactions', []));
        /** @var list<array{productId: string, purchaseToken: string}> $purchases */
        $purchases = array_values((array) $request->validated('purchases', []));

        $result = $request->validated('store') === 'apple' ? $stores->syncApple($user, $transactions) : $stores->syncGoogle($user, $purchases);
        $billing = $state->show($user->refresh());

        // Nothing linked: say why (another account's, or none of them good).
        if ($result['linked'] === 0 && $result['elsewhere'] > 0) {
            throw new Refusal(409, 'store_linked_elsewhere', ['billing' => $billing]);
        }

        if ($result['linked'] === 0 && $result['invalid'] > 0) {
            throw new Refusal(422, 'invalid_purchase');
        }

        return response()->json(['data' => $billing]);
    }
}
