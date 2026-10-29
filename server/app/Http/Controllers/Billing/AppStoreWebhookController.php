<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\StoreSubscriptions;
use App\Exceptions\Refusal;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * App Store Server Notifications V2: Apple posts `{ "signedPayload": "<JWS>" }`, which is checked
 * against Apple's certificate chain before anything is read. A good one is 200, also when it's
 * about something not ours (Apple retries anything else); a payload that fails the check is 400.
 */
final class AppStoreWebhookController extends Controller
{
    public function __invoke(Request $request, StoreSubscriptions $stores): Response
    {
        $payload = $request->input('signedPayload');

        if (! is_string($payload) || $payload === '') {
            return response('', 400);
        }

        try {
            $stores->appleNotification($payload);
        } catch (Refusal $refusal) {
            return response('', $refusal->refusalCode === 'invalid_purchase' ? 400 : 503);
        }

        return response('', 200);
    }
}
