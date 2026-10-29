<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Models\EmailSuppression;
use App\Services\EventSchedule\Signature;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * POST /webhooks/mail (docs/backend-plan.md D9; iaw's MailWebhookController's role): bounces and
 * complaints from Cloudflare Email Service, forwarded by our small Worker from its queue and
 * signed with our shared secret (`Stackapella-Signature: t=,v2=`, 5 minutes). A hard bounce or a
 * complaint is suppressed here too, so the person is told in the app to fix their address; a soft
 * bounce passes (Cloudflare holds it for a day itself).
 */
final class MailWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $secret = (string) config('services.cloudflare_email.webhook_secret');

        if ($secret === '' || ! Signature::valid($secret, $request->getContent(), (string) $request->header('Stackapella-Signature'), now()->getTimestamp())) {
            return new Response('Invalid signature', $secret === '' ? 404 : 400);
        }

        $suppressed = 0;

        foreach ((array) $request->json('events', []) as $event) {
            if (! is_array($event) || ! is_string($event['recipient'] ?? null) || filter_var($event['recipient'], FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }

            $reason = match (true) {
                ($event['type'] ?? null) === 'complaint' => 'complaint',
                ($event['type'] ?? null) === 'bounce' && ($event['bounceType'] ?? 'hard') !== 'soft' => 'permanent_bounce',
                default => null,
            };

            if ($reason !== null) {
                EmailSuppression::record($event['recipient'], $reason);
                $suppressed++;
            }
        }

        return new Response("Suppressed {$suppressed}", 200);
    }
}
