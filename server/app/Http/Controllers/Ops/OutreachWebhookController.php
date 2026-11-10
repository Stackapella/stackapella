<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ops;

use App\Actions\Outreach\OutreachReplies;
use App\Http\Controllers\Controller;
use App\Services\EventSchedule\Signature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * POST /webhooks/outreach (docs/outreach-setup.md): the outreach subdomain's Worker posts what
 * happened to Hillel's emails (delivered, a bounce, a complaint) and each reply that reached its
 * Email Routing address, with the reply's text, signed with our shared secret as /webhooks/mail is
 * (`Stackapella-Signature: t=,v2=`, 5 minutes). 404 while outreach has no secret. The same signed
 * post twice (a replay) is taken once: its signature is remembered for longer than it stays valid.
 */
final class OutreachWebhookController extends Controller
{
    public function __invoke(Request $request, OutreachReplies $replies): Response
    {
        $secret = (string) config('outreach.webhook_secret');

        if ($secret === '' || ! Signature::valid($secret, $request->getContent(), (string) $request->header('Stackapella-Signature'), now()->getTimestamp())) {
            return new Response('Invalid signature', $secret === '' ? 404 : 400);
        }

        if (! Cache::add('outreach-webhook:'.hash('sha256', (string) $request->header('Stackapella-Signature')), true, now()->addMinutes(10))) {
            return new Response('Took 0', 200);
        }

        $n = 0;

        foreach (array_slice((array) $request->json('events', []), 0, 100) as $event) {
            if (is_array($event) && in_array($event['type'] ?? null, ['delivered', 'bounce', 'complaint', 'reply'], true)) {
                $replies->event($event);
                $n++;
            }
        }

        return new Response("Took {$n}", 200);
    }
}
