<?php

declare(strict_types=1);

namespace App\Http\Controllers\Books;

use App\Actions\Books\PlusInvoices;
use App\Http\Controllers\Controller;
use App\Models\InvoiceNinjaConnection;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Invoice Ninja's "payment made" webhook for Stackapella's own books (/webhooks/invoice-ninja/
 * <connection>, with the secret we gave it in `X-Webhook-Secret`): for Plus paid by invoice.
 * Nothing in the body is trusted beyond the payment's id, which is read back from Invoice Ninja.
 */
final class InvoiceNinjaWebhookController extends Controller
{
    public function __invoke(Request $request, string $connection, PlusInvoices $invoices): Response
    {
        $found = InvoiceNinjaConnection::query()->whereSqid($connection)->where('owner_key', InvoiceNinjaConnection::PLATFORM)->first();
        $secret = (string) $request->header('X-Webhook-Secret');

        if ($found === null || (string) $found->webhook_secret === '' || ! hash_equals((string) $found->webhook_secret, $secret)) {
            return new Response('Unknown webhook', 404);
        }

        $id = $request->json('id');

        if (! is_string($id) || $id === '') {
            return new Response('Webhook ignored', 200);
        }

        $invoices->paid($found, $id);

        return new Response('Webhook handled', 200);
    }
}
