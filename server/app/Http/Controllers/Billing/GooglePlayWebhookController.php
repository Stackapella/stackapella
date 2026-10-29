<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\StoreSubscriptions;
use App\Exceptions\Refusal;
use App\Http\Controllers\Controller;
use App\Services\Auth\ProviderTokens;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Google Play's real-time developer notifications, by Pub/Sub push: the push's token must be
 * Google's, for our address, as the configured service account; the message's data is the
 * notification (base64 JSON). Pub/Sub retries anything but a 2xx, so a Play that doesn't answer
 * is 503 and comes again.
 */
final class GooglePlayWebhookController extends Controller
{
    public function __invoke(Request $request, StoreSubscriptions $stores, ProviderTokens $tokens): Response
    {
        $token = $request->bearerToken();

        if ($token === null || ! $tokens->googlePush($token)) {
            return response('', 401);
        }

        $data = $request->input('message.data');
        $message = is_string($data) ? json_decode((string) base64_decode($data, true), true) : null;

        if (! is_array($message)) {
            return response('', 204);
        }

        try {
            $stores->googleNotification($message);
        } catch (Refusal $refusal) {
            return response('', $refusal->getStatusCode() === 503 ? 503 : 204);
        }

        return response('', 204);
    }
}
