<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Actions\Coaching\CoachSetup;
use App\Http\Controllers\Controller;
use App\Models\CoachProfile;
use App\Models\StripeEvent;
use App\Services\Coaching\CoachingGateway;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use RuntimeException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;

/**
 * Stripe Connect's webhook (/webhooks/stripe-connect, its own signing secret): coaches' Express
 * accounts as they're onboarded or change (`account.updated`), so a coach can sell once Stripe says
 * their account can take charges.
 */
final class StripeConnectWebhookController extends Controller
{
    public function __invoke(Request $request, CoachSetup $setup, CoachingGateway $gateway): Response
    {
        $secret = (string) config('services.stripe_connect.webhook_secret');

        if ($secret === '') {
            // Unsigned events only on a laptop or in the tests, never on a server.
            if (! app()->environment('local', 'testing')) {
                report(new RuntimeException('Stripe Connect webhook refused: STRIPE_CONNECT_WEBHOOK_SECRET is not set.'));

                return new Response('Webhook secret missing', 403);
            }
        } else {
            try {
                Webhook::constructEvent($request->getContent(), (string) $request->header('Stripe-Signature'), $secret);
            } catch (SignatureVerificationException|UnexpectedValueException) {
                return new Response('Invalid signature', 400);
            }
        }

        $id = $request->input('id');

        if (is_string($id) && StripeEvent::query()->where('stripe_id', $id)->exists()) {
            return new Response('Webhook handled', 200);
        }

        // The account as Stripe has it now, not as this event said: events can arrive out of order,
        // and a stale "charges enabled" mustn't let a coach sell again.
        $account = (string) $request->input('data.object.id', '');

        if ($request->input('type') === 'account.updated' && $account !== '' && CoachProfile::query()->where('stripe_account_id', $account)->exists()) {
            $setup->accountUpdated($account, $gateway->account($account));
        }

        if (is_string($id)) {
            try {
                (new StripeEvent(['stripe_id' => $id, 'type' => (string) $request->input('type')]))->save();
            } catch (UniqueConstraintViolationException) {
                report(new RuntimeException('A Stripe Connect event was handled twice at once: '.$id));
            }
        }

        return new Response('Webhook handled', 200);
    }
}
