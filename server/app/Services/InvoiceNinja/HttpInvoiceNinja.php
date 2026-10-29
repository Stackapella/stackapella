<?php

declare(strict_types=1);

namespace App\Services\InvoiceNinja;

use App\Models\InvoiceNinjaConnection;
use App\Support\Http\PublicAddress;
use App\Support\Http\ResponseCap;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Invoice Ninja's v5 API (a port of Event Schedule's app/Utils/InvoiceNinja.php, on Laravel's
 * Http), keeping its lessons: the API address is built by suffix, never `rtrim()` (which cut
 * hosts ending in a, p, i, v or 1); a 200 isn't proof (the record must come back with an id);
 * redirects are never followed (a POST turned GET creates nothing, and the token header would go
 * along); the token is trimmed; a User-Agent is sent (an empty one is blocked by many firewalls).
 * A selfhosted address must be public and each call is pinned to the address checked.
 */
final class HttpInvoiceNinja implements InvoiceNinja
{
    public const HOSTED = 'https://invoicing.co';

    /** Invoice Ninja's own ids for the currencies we sell in. */
    private const CURRENCY_IDS = ['usd' => '1', 'gbp' => '2', 'eur' => '3'];

    public function company(InvoiceNinjaConnection $connection): array
    {
        $data = $this->send($connection, 'POST', 'companies/current');

        return ['id' => (string) ($data['id'] ?? ''), 'name' => (string) ($data['settings']['name'] ?? $data['name'] ?? '')];
    }

    public function findClient(InvoiceNinjaConnection $connection, string $email, string $currency): ?string
    {
        foreach ($this->send($connection, 'GET', 'clients', ['is_deleted' => 'false', 'email' => $email]) as $client) {
            if (is_array($client) && (string) ($client['settings']['currency_id'] ?? '') === (self::CURRENCY_IDS[strtolower($currency)] ?? '') && isset($client['id'])) {
                return (string) $client['id'];
            }
        }

        return null;
    }

    public function createClient(InvoiceNinjaConnection $connection, string $name, string $email, string $currency): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $last = count($parts) > 1 ? (string) array_pop($parts) : '';

        return $this->id($this->send($connection, 'POST', 'clients', [
            'name' => $name,
            'settings' => ['currency_id' => self::CURRENCY_IDS[strtolower($currency)] ?? '1'],
            'contacts' => [['first_name' => implode(' ', $parts), 'last_name' => $last, 'email' => $email]],
        ]));
    }

    public function findInvoice(InvoiceNinjaConnection $connection, string $poNumber): ?array
    {
        // Invoice Ninja's filter is a loose search: the newest 100 are read, and only an exact match counts.
        foreach ($this->send($connection, 'GET', 'invoices', ['filter' => $poNumber, 'include' => 'payments', 'per_page' => 100, 'sort' => 'id|desc']) as $invoice) {
            if (is_array($invoice) && ($invoice['po_number'] ?? null) === $poNumber && isset($invoice['id'])) {
                $payment = collect((array) ($invoice['payments'] ?? []))->first(fn (mixed $p): bool => is_array($p) && ! ($p['is_deleted'] ?? false));

                return ['id' => (string) $invoice['id'], 'paymentId' => is_array($payment) && isset($payment['id']) ? (string) $payment['id'] : null];
            }
        }

        return null;
    }

    public function createInvoice(InvoiceNinjaConnection $connection, string $clientId, array $lines, string $poNumber, string $date, bool $sendEmail): string
    {
        return $this->id($this->send($connection, 'POST', 'invoices?'.($sendEmail ? 'send_email=true' : 'mark_sent=true'), [
            'client_id' => $clientId,
            'po_number' => $poNumber,
            'date' => $date,
            'line_items' => array_map(static fn (array $line): array => ['notes' => $line['description'], 'cost' => self::money($line['cents']), 'quantity' => 1], $lines),
        ]));
    }

    public function createPayment(InvoiceNinjaConnection $connection, string $clientId, string $invoiceId, int $cents, string $date, string $reference): string
    {
        return $this->id($this->send($connection, 'POST', 'payments', [
            'client_id' => $clientId,
            'amount' => self::money($cents),
            'date' => $date,
            'transaction_reference' => $reference,
            'invoices' => [['invoice_id' => $invoiceId, 'amount' => self::money($cents)]],
        ]));
    }

    public function refundedOn(InvoiceNinjaConnection $connection, string $paymentId): int
    {
        return (int) round(((float) ($this->send($connection, 'GET', 'payments/'.rawurlencode($paymentId))['refunded'] ?? 0)) * 100);
    }

    public function refund(InvoiceNinjaConnection $connection, string $paymentId, string $invoiceId, int $cents, string $date): void
    {
        $this->send($connection, 'POST', 'payments/refund', [
            'id' => $paymentId,
            'amount' => self::money($cents),
            'date' => $date,
            'invoices' => [['invoice_id' => $invoiceId, 'amount' => self::money($cents)]],
        ]);
    }

    public function vendor(InvoiceNinjaConnection $connection, string $name): string
    {
        foreach ($this->send($connection, 'GET', 'vendors', ['name' => $name, 'is_deleted' => 'false']) as $vendor) {
            if (is_array($vendor) && ($vendor['name'] ?? null) === $name && isset($vendor['id'])) {
                return (string) $vendor['id'];
            }
        }

        return $this->id($this->send($connection, 'POST', 'vendors', ['name' => $name]));
    }

    public function findExpense(InvoiceNinjaConnection $connection, string $reference): ?string
    {
        foreach ($this->send($connection, 'GET', 'expenses', ['filter' => $reference, 'per_page' => 100, 'sort' => 'id|desc']) as $expense) {
            if (is_array($expense) && ($expense['transaction_reference'] ?? null) === $reference && isset($expense['id'])) {
                return (string) $expense['id'];
            }
        }

        return null;
    }

    public function createExpense(InvoiceNinjaConnection $connection, string $vendorId, int $cents, string $currency, string $date, string $note, string $reference): string
    {
        return $this->id($this->send($connection, 'POST', 'expenses', [
            'vendor_id' => $vendorId,
            'amount' => self::money($cents),
            'currency_id' => self::CURRENCY_IDS[strtolower($currency)] ?? '1',
            'date' => $date,
            'public_notes' => $note,
            'transaction_reference' => $reference,
        ]));
    }

    public function payment(InvoiceNinjaConnection $connection, string $paymentId): ?array
    {
        try {
            $data = $this->send($connection, 'GET', 'payments/'.rawurlencode($paymentId), ['include' => 'paymentables']);
        } catch (InvoiceNinjaError $e) {
            if ($e->reason === InvoiceNinjaError::REFUSED) {
                return null;
            }

            throw $e;
        }

        return [
            'id' => (string) ($data['id'] ?? ''),
            'cents' => (int) round(((float) ($data['amount'] ?? 0)) * 100),
            'invoiceIds' => array_values(array_filter(array_map(static fn (mixed $p): ?string => is_array($p) && isset($p['invoice_id']) ? (string) $p['invoice_id'] : null, (array) ($data['paymentables'] ?? [])))),
            'isDeleted' => (bool) ($data['is_deleted'] ?? false),
        ];
    }

    public function paidOn(InvoiceNinjaConnection $connection, string $invoiceId): int
    {
        $invoice = $this->send($connection, 'GET', 'invoices/'.rawurlencode($invoiceId), ['include' => 'payments.paymentables']);
        $paid = 0;

        foreach ((array) ($invoice['payments'] ?? []) as $payment) {
            // Invoice Ninja's payment statuses: 4 completed, 5 partly refunded (1 pending, 2
            // voided, 3 failed, 6 refunded).
            if (! is_array($payment) || ($payment['is_deleted'] ?? false) || ! in_array((int) ($payment['status_id'] ?? 0), [4, 5], true)) {
                continue;
            }

            $applied = collect((array) ($payment['paymentables'] ?? []))->filter(fn (mixed $p): bool => is_array($p) && (string) ($p['paymentable_id'] ?? $p['invoice_id'] ?? '') === $invoiceId)
                ->sum(fn (array $p): float => (float) ($p['amount'] ?? 0) - (float) ($p['refunded'] ?? 0));
            $paid += (int) round(($applied > 0 ? $applied : (float) ($payment['amount'] ?? 0) - (float) ($payment['refunded'] ?? 0)) * 100);
        }

        return $paid;
    }

    public function createWebhook(InvoiceNinjaConnection $connection, string $url, string $secret): string
    {
        // Event 4: a payment was made.
        return $this->id($this->send($connection, 'POST', 'webhooks', ['target_url' => $url, 'event_id' => '4', 'rest_method' => 'post', 'format' => 'JSON', 'headers' => ['X-Webhook-Secret' => $secret]]));
    }

    public function deleteWebhook(InvoiceNinjaConnection $connection, string $id): void
    {
        $this->send($connection, 'POST', 'webhooks/bulk', ['ids' => [$id], 'action' => 'delete']);
    }

    /**
     * The API's base from the address the coach gave (hosted when blank), keeping a sub-path an
     * install is mounted under and dropping a trailing /api/v1. False when it isn't usable.
     */
    public static function normalizeApiUrl(?string $apiUrl): string|false
    {
        $url = trim((string) $apiUrl);

        if ($url === '') {
            return self::HOSTED.'/api/v1/';
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $url) !== 1) {
            $url = 'https://'.$url;
        }

        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host']) || ! in_array(strtolower($parts['scheme']), ['http', 'https'], true) || isset($parts['user']) || isset($parts['query'])) {
            return false;
        }

        $base = strtolower($parts['scheme']).'://'.strtolower($parts['host']).(isset($parts['port']) ? ':'.$parts['port'] : '');
        $path = rtrim((string) preg_replace('#/api/v1/?$#i', '', rtrim((string) ($parts['path'] ?? ''), '/')), '/');

        return $base.$path.'/api/v1/';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int|string, mixed>
     */
    private function send(InvoiceNinjaConnection $connection, string $method, string $route, array $data = []): array
    {
        $base = self::normalizeApiUrl($connection->api_url);

        if ($base === false) {
            throw new InvoiceNinjaError(InvoiceNinjaError::NOT_ALLOWED);
        }

        // A self-hosted install is someone else's server: its answers are capped in size.
        $options = ['allow_redirects' => false, ...ResponseCap::options()];

        // Stackapella's own hosted Invoice Ninja is trusted; any other address is checked and pinned.
        if (! str_starts_with($base, self::HOSTED.'/')) {
            $pin = PublicAddress::resolve($base);

            if ($pin === false) {
                throw new InvoiceNinjaError(InvoiceNinjaError::NOT_ALLOWED);
            }

            if ($pin['ip'] !== null) {
                $options['curl'] = [CURLOPT_RESOLVE => ["{$pin['host']}:{$pin['port']}:{$pin['ip']}"]];
            }
        }

        $request = Http::baseUrl($base)->acceptJson()->timeout(12)->connectTimeout(5)->withOptions($options)->withHeaders([
            'X-API-TOKEN' => trim((string) $connection->token),
            'X-Requested-With' => 'XMLHttpRequest',
            'X-CLIENT-PLATFORM' => 'Stackapella',
            'User-Agent' => 'Stackapella/1.0',
        ]);

        try {
            $response = $request->send($method, $route, $method === 'GET' ? ['query' => $data] : ['json' => $data]);
        } catch (ConnectionException|RequestException $e) {
            throw new InvoiceNinjaError(preg_match('/cURL error (35|51|53|54|58|59|60|64|66|77|80|82|83|90|91)\b/', $e->getMessage()) === 1 ? InvoiceNinjaError::TLS : InvoiceNinjaError::UNREACHABLE, $e->getMessage(), $e);
        }

        return $this->data($response);
    }

    /**
     * @return array<int|string, mixed>
     */
    private function data(Response $response): array
    {
        if ($response->redirect()) {
            throw new InvoiceNinjaError(InvoiceNinjaError::REDIRECTED, "Invoice Ninja redirected ({$response->status()}).");
        }

        if (in_array($response->status(), [401, 403], true)) {
            throw new InvoiceNinjaError(InvoiceNinjaError::BAD_TOKEN, "Invoice Ninja refused the token ({$response->status()}).");
        }

        if ($response->serverError() || $response->status() === 429) {
            throw new InvoiceNinjaError(InvoiceNinjaError::UNREACHABLE, "Invoice Ninja answered {$response->status()}.");
        }

        $data = $response->json('data');

        if (! $response->successful() || ! is_array($data)) {
            throw new InvoiceNinjaError(InvoiceNinjaError::REFUSED, 'Invoice Ninja refused ('.$response->status().'): '.mb_substr($response->body(), 0, 300));
        }

        return $data;
    }

    /**
     * @param  array<int|string, mixed>  $data
     */
    private function id(array $data): string
    {
        if (! isset($data['id']) || (string) $data['id'] === '') {
            throw new InvoiceNinjaError(InvoiceNinjaError::REFUSED, 'Invoice Ninja answered but made nothing.');
        }

        return (string) $data['id'];
    }

    private static function money(int $cents): float
    {
        return round($cents / 100, 2);
    }
}
