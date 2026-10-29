<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\CheckoutSession;
use App\Models\User;
use App\Support\Usage\Plans;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Where Stripe sends someone after Checkout (/plus/return): a page that works signed out, as on an
 * iPhone Stripe opens outside the installed app. It says whether Plus has started yet.
 */
final class CheckoutReturnController extends Controller
{
    public function __invoke(Request $request, Plans $plans): View
    {
        $session = $request->query('session');
        $checkout = is_string($session) ? CheckoutSession::query()->where('stripe_id', $session)->first() : null;

        // Done once Stripe said so and the account really is on Plus (a refused trial isn't).
        $owner = $checkout?->completed_at !== null ? User::query()->find($checkout->user_id) : null;

        return view('billing.return', ['done' => $owner !== null && $plans->isPlus($owner)]);
    }
}
