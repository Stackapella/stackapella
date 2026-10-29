<?php

declare(strict_types=1);

namespace App\Http\Controllers\EventSchedule;

use App\Http\Controllers\Controller;
use App\Jobs\HandleEventScheduleDelivery;
use App\Models\EsConnection;
use App\Models\EsDelivery;
use App\Services\EventSchedule\Signature;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Event Schedule's appointment webhooks (/webhooks/event-schedule/<connection>; contract/event-
 * schedule.md, 4): each connected schedule's own secret, the v2 signature with a 5-minute window,
 * each delivery handled once, and the handling queued.
 */
final class WebhookController extends Controller
{
    public function __invoke(Request $request, string $connection): Response
    {
        $found = EsConnection::query()->whereSqid($connection)->first();

        if ($found === null || (string) $found->webhook_secret === '') {
            return new Response('Unknown webhook', 404);
        }

        $secret = (string) $found->webhook_secret;

        if (! Signature::valid($secret, $request->getContent(), (string) $request->header(Signature::HEADER), now()->getTimestamp())) {
            return new Response('Invalid signature', 400);
        }

        $id = $request->input('id');
        $type = $request->input('type');

        if (! is_string($id) || $id === '' || ! is_string($type) || ! str_starts_with($type, 'appointment.')) {
            return new Response('Webhook ignored', 200);
        }

        try {
            (new EsDelivery(['delivery_id' => mb_substr($id, 0, 64), 'type' => mb_substr($type, 0, 40)]))->forceFill(['es_connection_id' => $found->id])->save();
        } catch (UniqueConstraintViolationException) {
            return new Response('Webhook handled', 200);
        }

        HandleEventScheduleDelivery::dispatch($found->id, (array) $request->json()->all());

        return new Response('Webhook handled', 200);
    }
}
