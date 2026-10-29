<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Billing;

use App\Actions\Billing\StartCheckout;
use App\Exceptions\Refusal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\CheckoutRequest;
use App\Models\User;
use App\Services\Billing\BillingGateway;
use App\Support\Billing\BillingState;
use App\Support\Billing\Withdrawal;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * billing.prices, .show, .checkout and .portal.
 */
final class BillingController extends Controller
{
    public function prices(Request $request, BillingState $state): JsonResponse
    {
        $user = $request->user();

        return response()->json(['data' => [
            'prices' => BillingState::prices(),
            'trial' => $state->trial($user instanceof User ? $user : null),
            // Ask for the consent to start at once (the EU, EEA and UK withdrawal right).
            'startNowConsent' => Withdrawal::needsConsent(Withdrawal::country($request)),
        ]]);
    }

    public function show(BillingState $state, #[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => $state->show($user)]);
    }

    public function checkout(CheckoutRequest $request, StartCheckout $checkout, #[CurrentUser] User $user): JsonResponse
    {
        /** @var 'month'|'year' $interval */
        $interval = (string) $request->validated('interval');
        $url = $checkout->handle($user, $interval, (string) $request->validated('currency'), $request->boolean('trial'), (string) ($request->validated('moment') ?? 'other'), Withdrawal::country($request), $request->boolean('startNowConsent'));

        return response()->json(['data' => ['url' => $url]], 201);
    }

    public function portal(BillingGateway $gateway, #[CurrentUser] User $user): JsonResponse
    {
        if ((string) config('cashier.secret') === '') {
            throw new Refusal(423, 'paused');
        }

        if (! $user->hasStripeId()) {
            throw new Refusal(409, 'no_subscription');
        }

        return response()->json(['data' => ['url' => $gateway->portalUrl($user, url('/settings/plus'))]], 201);
    }
}
